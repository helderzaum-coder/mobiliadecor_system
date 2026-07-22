<?php

namespace App\Filament\Pages;

use App\Models\CategoriaFinanceira;
use App\Models\ContaBancaria;
use App\Models\ContaPagar;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportarTransacoesML extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Planilhas';
    protected static ?string $navigationLabel = 'Transações ML';
    protected static ?string $title = 'Importar Transações ML';
    protected static string $view = 'filament.pages.importar-transacoes-ml';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'status' => 'pago',
            'forma_pagamento' => 'debito_automatico',
            'numero_parcela' => 1,
            'total_parcelas' => 1,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Configurações do Lançamento')->schema([
                Forms\Components\Select::make('categoria_id')
                    ->label('Categoria')
                    ->options(fn () => CategoriaFinanceira::whereIn('tipo', ['saida', 'ambos'])
                        ->where('ativo', true)
                        ->where('sistema', false)
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->toArray())
                    ->searchable()
                    ->required()
                    ->placeholder('Selecione a categoria'),

                Forms\Components\Select::make('conta_bancaria_id')
                    ->label('Banco')
                    ->options(fn () => ContaBancaria::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray())
                    ->searchable()
                    ->required()
                    ->placeholder('Selecione o banco'),

                Forms\Components\Select::make('forma_pagamento')
                    ->label('Forma de Pagamento')
                    ->options([
                        'pix' => 'Pix',
                        'boleto' => 'Boleto',
                        'cartao' => 'Cartão',
                        'transferencia' => 'Transferência',
                        'dinheiro' => 'Dinheiro',
                        'debito_automatico' => 'Débito Automático',
                    ])
                    ->required()
                    ->default('debito_automatico'),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pago' => 'Pago',
                        'pendente' => 'Pendente',
                    ])
                    ->required()
                    ->default('pago'),
            ])->columns(2),

            Forms\Components\Section::make('Arquivo CSV')->schema([
                Forms\Components\FileUpload::make('arquivo')
                    ->label('Arquivo CSV')
                    ->helperText('Formato: data;valor;descricao — Ex: 01/07/2026;25.94;Antecipação ML')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/octet-stream', '.csv'])
                    ->required()
                    ->disk('local')
                    ->directory('temp-imports'),
            ]),
        ])->statePath('data');
    }

    public function processar(): void
    {
        try {
            $data = $this->form->getState();
        } catch (\Exception $e) {
            $this->form->fill();
            Notification::make()->title('Arquivo expirou. Faça o upload novamente.')->danger()->send();
            return;
        }

        $relativePath = is_array($data['arquivo']) ? reset($data['arquivo']) : $data['arquivo'];
        $path = null;

        foreach ([
            storage_path('app/' . $relativePath),
            storage_path('app/private/' . $relativePath),
        ] as $p) {
            if (file_exists($p)) { $path = $p; break; }
        }

        if (!$path) {
            Notification::make()->title('Arquivo não encontrado.')->danger()->send();
            return;
        }

        $linhas = array_filter(explode("\n", str_replace("\r", '', file_get_contents($path))));
        $importados = 0;
        $erros = [];

        foreach ($linhas as $i => $linha) {
            $num = $i + 1;
            $linha = trim($linha);
            if (empty($linha)) continue;

            // Pular cabeçalho
            if ($num === 1 && preg_match('/[a-zA-Z]{3,}/', explode(';', $linha)[0] ?? '')) {
                continue;
            }

            $cols = str_getcsv($linha, ';');
            if (count($cols) < 3) {
                $erros[] = "Linha {$num}: menos de 3 colunas";
                continue;
            }

            [$dataStr, $valorStr, $descricao] = [trim($cols[0]), trim($cols[1]), trim($cols[2])];

            // Parsear data dd/mm/aaaa ou aaaa-mm-dd
            $dataParsed = null;
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataStr, $m)) {
                $dataParsed = "{$m[3]}-{$m[2]}-{$m[1]}";
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataStr)) {
                $dataParsed = $dataStr;
            }

            if (!$dataParsed) {
                $erros[] = "Linha {$num}: data inválida '{$dataStr}'";
                continue;
            }

            $valor = round((float) str_replace(['.', ','], ['', '.'], $valorStr), 2);
            if ($valor <= 0) {
                $erros[] = "Linha {$num}: valor inválido '{$valorStr}'";
                continue;
            }

            if (empty($descricao)) {
                $erros[] = "Linha {$num}: descrição vazia";
                continue;
            }

            ContaPagar::create([
                'descricao'        => $descricao,
                'valor_parcela'    => $valor,
                'data_lancamento'  => $dataParsed,
                'data_vencimento'  => $dataParsed,
                'data_pagamento'   => $data['status'] === 'pago' ? $dataParsed : null,
                'status'           => $data['status'],
                'forma_pagamento'  => $data['forma_pagamento'],
                'conta_bancaria_id' => $data['conta_bancaria_id'],
                'categoria_id'     => $data['categoria_id'],
                'numero_parcela'   => 1,
                'total_parcelas'   => 1,
                'lancamento_manual' => true,
            ]);

            $importados++;
        }

        @unlink($path);

        $msg = "{$importados} lançamento(s) importado(s).";
        if (!empty($erros)) {
            $msg .= ' ' . count($erros) . ' erro(s): ' . implode(' | ', array_slice($erros, 0, 5));
        }

        Notification::make()
            ->title($msg)
            ->{$importados > 0 ? 'success' : 'warning'}()
            ->send();

        $this->data = [];
        $this->form->fill([
            'status' => 'pago',
            'forma_pagamento' => 'debito_automatico',
            'numero_parcela' => 1,
            'total_parcelas' => 1,
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
