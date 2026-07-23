<?php

namespace App\Filament\Pages;

use App\Models\CategoriaFinanceira;
use App\Models\ContaBancaria;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LancamentosEmLote extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Lançamentos em Lote';
    protected static ?string $title = 'Lançamentos em Lote';
    protected static string $view = 'filament.pages.lancamentos-em-lote';

    public ?array $data = [];
    public array $preview = [];

    public function mount(): void
    {
        $this->form->fill([
            'tipo'           => 'saida',
            'status'         => 'pago',
            'forma_pagamento' => 'debito_automatico',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Configurações')->schema([
                Forms\Components\Select::make('tipo')
                    ->label('Tipo de Lançamento')
                    ->options([
                        'entrada' => '▲ Entrada (recebimento)',
                        'saida'   => '▼ Saída (pagamento)',
                    ])
                    ->required()
                    ->default('saida')
                    ->reactive(),

                Forms\Components\Select::make('categoria_id')
                    ->label('Categoria')
                    ->options(fn ($get) => CategoriaFinanceira::where('ativo', true)
                        ->where('sistema', false)
                        ->when($get('tipo') === 'entrada', fn ($q) => $q->whereIn('tipo', ['entrada', 'ambos']))
                        ->when($get('tipo') === 'saida',   fn ($q) => $q->whereIn('tipo', ['saida', 'ambos']))
                        ->orderBy('nome')->pluck('nome', 'id')->toArray())
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
                        'pix'               => 'Pix',
                        'boleto'            => 'Boleto',
                        'cartao'            => 'Cartão',
                        'transferencia'     => 'Transferência',
                        'dinheiro'          => 'Dinheiro',
                        'debito_automatico' => 'Débito Automático',
                    ])
                    ->required()
                    ->default('debito_automatico'),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(fn ($get) => $get('tipo') === 'entrada'
                        ? ['recebido' => 'Recebido', 'pendente' => 'Pendente']
                        : ['pago' => 'Pago', 'pendente' => 'Pendente'])
                    ->required()
                    ->default('pago')
                    ->reactive(),
            ])->columns(3),

            Forms\Components\Section::make('Dados — cole cada coluna do Excel')->schema([
                Forms\Components\Textarea::make('col_datas')
                    ->label('📅 Datas')
                    ->helperText('Ex: 01/07/2026 ou 01/07/2026 08:27')
                    ->rows(10)
                    ->required(),

                Forms\Components\Textarea::make('col_descricoes')
                    ->label('📝 Descrições')
                    ->helperText('Uma por linha')
                    ->rows(10)
                    ->required(),

                Forms\Components\Textarea::make('col_valores')
                    ->label('💰 Valores')
                    ->helperText('Ex: 150,00 ou R$ 150,00 ou -150,00 (sinal ignorado)')
                    ->rows(10)
                    ->required(),
            ])->columns(3),
        ])->statePath('data');
    }

    private function parseLinhas(): array
    {
        $data = $this->form->getState();

        $datas      = array_values(array_filter(explode("\n", str_replace("\r", '', $data['col_datas'])),      fn ($l) => trim($l) !== ''));
        $descricoes = array_values(array_filter(explode("\n", str_replace("\r", '', $data['col_descricoes'])), fn ($l) => trim($l) !== ''));
        $valores    = array_values(array_filter(explode("\n", str_replace("\r", '', $data['col_valores'])),    fn ($l) => trim($l) !== ''));

        $total  = max(count($datas), count($descricoes), count($valores));
        $linhas = [];

        for ($i = 0; $i < $total; $i++) {
            $dataStr   = trim($datas[$i] ?? '');
            $descricao = trim($descricoes[$i] ?? 'Lançamento');
            $valorStr  = trim($valores[$i] ?? '');

            $dataParsed = null;
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{2,4})/', $dataStr, $m)) {
                $ano = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
                $dataParsed = "{$ano}-{$m[2]}-{$m[1]}";
            } elseif (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $dataStr, $m)) {
                $dataParsed = $m[0];
            }

            $valorLimpo = preg_replace('/[^\d,.-]/', '', $valorStr);
            if (preg_match('/,\d{1,2}$/', $valorLimpo)) {
                $valorLimpo = str_replace('.', '', $valorLimpo);
                $valorLimpo = str_replace(',', '.', $valorLimpo);
            }
            $valor = round(abs((float) $valorLimpo), 2);

            $linhas[] = [
                'data'      => $dataParsed,
                'descricao' => $descricao,
                'valor'     => $valor,
                'data_raw'  => $dataStr,
                'valor_raw' => $valorStr,
                'linha'     => $i + 1,
            ];
        }

        return [$data, $linhas];
    }

    public function visualizar(): void
    {
        try {
            [, $linhas] = $this->parseLinhas();
        } catch (\Exception $e) {
            Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
            return;
        }

        $this->preview = $linhas;
    }

    public function processar(): void
    {
        try {
            [$data, $linhas] = $this->parseLinhas();
        } catch (\Exception $e) {
            Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
            return;
        }

        $importados = 0;
        $ignorados  = 0;

        foreach ($linhas as $linha) {
            if (!$linha['data'] || $linha['valor'] <= 0) {
                $ignorados++;
                continue;
            }

            if ($data['tipo'] === 'entrada') {
                ContaReceber::create([
                    'observacoes'       => $linha['descricao'],
                    'valor_parcela'     => $linha['valor'],
                    'data_vencimento'   => $linha['data'],
                    'data_recebimento'  => $data['status'] === 'recebido' ? $linha['data'] : null,
                    'status'            => $data['status'],
                    'forma_pagamento'   => $data['forma_pagamento'],
                    'conta_bancaria_id' => $data['conta_bancaria_id'],
                    'categoria_id'      => $data['categoria_id'],
                    'numero_parcela'    => 1,
                    'total_parcelas'    => 1,
                    'lancamento_manual' => true,
                ]);
            } else {
                ContaPagar::create([
                    'descricao'         => $linha['descricao'],
                    'valor_parcela'     => $linha['valor'],
                    'data_lancamento'   => $linha['data'],
                    'data_vencimento'   => $linha['data'],
                    'data_pagamento'    => $data['status'] === 'pago' ? $linha['data'] : null,
                    'status'            => $data['status'],
                    'forma_pagamento'   => $data['forma_pagamento'],
                    'conta_bancaria_id' => $data['conta_bancaria_id'],
                    'categoria_id'      => $data['categoria_id'],
                    'numero_parcela'    => 1,
                    'total_parcelas'    => 1,
                    'lancamento_manual' => true,
                ]);
            }

            $importados++;
        }

        $msg = "{$importados} lançamento(s) importado(s).";
        if ($ignorados > 0) $msg .= " {$ignorados} ignorado(s) (data ou valor inválido).";

        Notification::make()->title($msg)->{$importados > 0 ? 'success' : 'warning'}()->send();

        $this->preview = [];
        $this->data    = [];
        $this->form->fill(['tipo' => 'saida', 'status' => 'pago', 'forma_pagamento' => 'debito_automatico']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
