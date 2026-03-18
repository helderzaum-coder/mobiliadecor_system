<?php

namespace App\Filament\Pages;

use App\Models\Transportadora;
use App\Services\TransportadoraTaxaImportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportarTabelaTransportadora extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $navigationLabel = 'Importar Tabela Transportadora';
    protected static ?string $title = 'Importar Tabela de Transportadora';
    protected static string $view = 'filament.pages.importar-tabela-transportadora';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'id_transportadora' => null,
            'tipo_importacao' => null,
            'limpar_antes' => false,
            'arquivo' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('id_transportadora')
                ->label('Transportadora')
                ->options(Transportadora::where('ativo', true)->pluck('nome_transportadora', 'id_transportadora'))
                ->required()
                ->searchable(),
            Forms\Components\Select::make('tipo_importacao')
                ->label('Tipo de Importação')
                ->options([
                    'taxas' => 'Taxas Especiais (TDA, TRT, TAR, TAS)',
                    'frete' => 'Tabela de Frete (faixas de peso/CEP)',
                ])
                ->required()
                ->reactive(),
            Forms\Components\Toggle::make('limpar_antes')
                ->label('Limpar dados existentes antes de importar')
                ->helperText('Remove todos os registros do tipo selecionado antes de importar')
                ->default(false),
            Forms\Components\FileUpload::make('arquivo')
                ->label('Planilha (.xlsx, .xls, .csv)')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'text/csv',
                    'application/octet-stream',
                    '.xlsx', '.xls', '.csv',
                ])
                ->required()
                ->directory('transportadora-planilhas')
                ->preserveFilenames()
                ->openable()
                ->deletable()
                ->maxSize(10240),
        ])->statePath('data');
    }

    public function processar(): void
    {
        // Aumentar limites para planilhas grandes
        set_time_limit(300);
        ini_set('memory_limit', '1G');

        try {
            $data = $this->form->getState();
        } catch (\League\Flysystem\UnableToRetrieveMetadata|\Exception $e) {
            // Arquivo temporário expirou/foi removido — resetar form
            $this->data = [];
            $this->form->fill([
                'id_transportadora' => null,
                'tipo_importacao' => null,
                'limpar_antes' => false,
                'arquivo' => null,
            ]);
            Notification::make()
                ->title('O arquivo enviado expirou. Faça o upload novamente.')
                ->danger()
                ->send();
            return;
        }

        $arquivo = $data['arquivo'] ?? null;
        $idTransportadora = (int) ($data['id_transportadora'] ?? 0);
        $tipo = $data['tipo_importacao'] ?? '';
        $limpar = (bool) ($data['limpar_antes'] ?? false);

        if (!$arquivo || !$idTransportadora || !$tipo) {
            Notification::make()->title('Preencha todos os campos.')->danger()->send();
            return;
        }

        $filePath = storage_path('app/public/' . $arquivo);
        if (!file_exists($filePath)) {
            $filePath = storage_path('app/' . $arquivo);
        }
        if (!file_exists($filePath)) {
            Notification::make()->title('Arquivo não encontrado.')->danger()->send();
            return;
        }

        $resultado = match ($tipo) {
            'taxas' => TransportadoraTaxaImportService::importarTaxas($filePath, $idTransportadora, $limpar),
            'frete' => TransportadoraTaxaImportService::importarTabelaFrete($filePath, $idTransportadora, $limpar),
            default => ['importados' => 0, 'erros' => 1, 'mensagens' => ['Tipo inválido']],
        };

        $msg = "Importados: {$resultado['importados']}";
        if ($resultado['erros'] > 0) {
            $msg .= " | Erros: {$resultado['erros']}";
        }
        if (!empty($resultado['mensagens'])) {
            $msg .= ' | ' . implode(', ', array_slice($resultado['mensagens'], 0, 3));
        }

        if ($resultado['importados'] > 0) {
            Notification::make()->title($msg)->success()->send();
        } else {
            Notification::make()->title($msg)->warning()->send();
        }

        // Reset completo do formulário
        $this->data = [];
        $this->form->fill([
            'id_transportadora' => null,
            'tipo_importacao' => null,
            'limpar_antes' => false,
            'arquivo' => null,
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
