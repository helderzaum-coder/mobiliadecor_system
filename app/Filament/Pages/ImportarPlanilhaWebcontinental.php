<?php

namespace App\Filament\Pages;

use App\Services\WebcontinentalPlanilhaService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportarPlanilhaWebcontinental extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Planilha Webcontinental';
    protected static ?string $title = 'Importar Planilha Webcontinental';
    protected static string $view = 'filament.pages.importar-planilha-webcontinental';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('arquivo')
                ->label('Planilha Webcontinental (.xlsx, .xls, .csv)')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'text/csv',
                    'application/octet-stream',
                    '.xlsx', '.xls', '.csv',
                ])
                ->required()
                ->directory('webcontinental-planilhas')
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

        $resultado = WebcontinentalPlanilhaService::processar($filePath);

        $msg = "Processados: {$resultado['processados']}";
        if (($resultado['ja_processados'] ?? 0) > 0) {
            $msg .= " | Já processados: {$resultado['ja_processados']}";
        }
        if (($resultado['com_divergencia'] ?? 0) > 0) {
            $msg .= " | Com divergência de comissão: {$resultado['com_divergencia']}";
        }
        if ($resultado['nao_encontrados'] > 0) {
            $msg .= " | Não encontrados: {$resultado['nao_encontrados']}";
        }
        if ($resultado['erros'] > 0) {
            $msg .= " | Erros: {$resultado['erros']}";
        }

        if ($resultado['processados'] > 0) {
            Notification::make()->title($msg)->success()->send();
        } else {
            Notification::make()->title($msg)->warning()->send();
        }

        if (!empty($resultado['detalhes'])) {
            Notification::make()
                ->title('Detalhes')
                ->body(implode("\n", array_slice($resultado['detalhes'], 0, 10)))
                ->warning()
                ->persistent()
                ->send();
        }

        $this->form->fill();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'operador']) ?? false;
    }
}
