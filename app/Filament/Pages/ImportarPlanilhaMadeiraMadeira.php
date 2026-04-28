<?php

namespace App\Filament\Pages;

use App\Services\MadeiraMadeiraPlanilhaService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportarPlanilhaMadeiraMadeira extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Planilha Madeira Madeira';
    protected static ?string $title = 'Importar Planilha Madeira Madeira';
    protected static string $view = 'filament.pages.importar-planilha-madeiramadeira';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('arquivo')
                ->label('Planilha Madeira Madeira (.csv)')
                ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                ->required()
                ->directory('mm-planilhas')
                ->preserveFilenames(),
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

        $resultado = MadeiraMadeiraPlanilhaService::processar($filePath);

        $msg = "Processados: {$resultado['processados']}";
        if ($resultado['erros'] > 0) {
            $msg .= " | Erros: {$resultado['erros']}";
        }

        Notification::make()->title($msg)->{$resultado['processados'] > 0 ? 'success' : 'warning'}()->send();
        $this->form->fill();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'operador']) ?? false;
    }
}
