<?php

namespace App\Filament\Pages;

use App\Services\MercadoLivrePlanilhaService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportarPlanilhaML extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Planilha Mercado Livre';
    protected static ?string $title = 'Importar Planilha Mercado Livre';
    protected static string $view = 'filament.pages.importar-planilha-ml';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['bling_account' => 'primary']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('bling_account')
                ->label('Conta Mercado Livre')
                ->options([
                    'primary'   => 'Mobilia Decor',
                    'secondary' => 'HES Móveis',
                ])
                ->required()
                ->default('primary'),
            Forms\Components\FileUpload::make('arquivo')
                ->label('Planilha ML (.xlsx, .xls, .csv)')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'text/csv',
                    'application/octet-stream',
                    '.xlsx',
                    '.xls',
                    '.csv',
                ])
                ->required()
                ->directory('ml-planilhas')
                ->preserveFilenames()
                ->openable(),
        ])->statePath('data');
    }

    public function processar(): void
    {
        try {
            $data = $this->form->getState();
        } catch (\Exception $e) {
            $this->data = [];
            $this->form->fill();
            Notification::make()->title('O arquivo enviado expirou. Faça o upload novamente.')->danger()->send();
            return;
        }

        $arquivo = $data['arquivo'] ?? null;
        $blingAccount = $data['bling_account'] ?? 'primary';

        if (!$arquivo) {
            Notification::make()->title('Selecione um arquivo.')->danger()->send();
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

        $resultado = MercadoLivrePlanilhaService::processar($filePath, $blingAccount);

        $msg = "Com rebate: {$resultado['processados']}";
        if ($resultado['sem_rebate'] > 0) {
            $msg .= " | Sem rebate: {$resultado['sem_rebate']}";
        }
        if ($resultado['nao_encontrados'] > 0) {
            $msg .= " | Não encontrados: {$resultado['nao_encontrados']}";
        }
        if ($resultado['erros'] > 0) {
            $msg .= " | Erros: {$resultado['erros']}";
        }

        if ($resultado['processados'] > 0 || $resultado['sem_rebate'] > 0) {
            Notification::make()->title($msg)->success()->send();
        } else {
            Notification::make()->title($msg)->warning()->send();
        }

        $this->data = [];
        $this->form->fill(['bling_account' => 'primary', 'arquivo' => null]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
