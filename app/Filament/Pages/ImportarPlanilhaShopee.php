<?php

namespace App\Filament\Pages;

use App\Services\ShopeePlanilhaService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportarPlanilhaShopee extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Planilha Shopee';
    protected static ?string $title = 'Importar Planilha Shopee';
    protected static string $view = 'filament.pages.importar-planilha-shopee';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('arquivo')
                ->label('Planilha Shopee (.xlsx, .xls, .csv)')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'text/csv',
                ])
                ->required()
                ->directory('shopee-planilhas')
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

        $resultado = ShopeePlanilhaService::processar($filePath);

        $msg = "Processados: {$resultado['processados']}";
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

        $this->form->fill();
    }

    public function corrigirDadosBling(bool $forcar = false): void
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

        $resultado = \App\Services\ShopeeCorrigirDadosService::processar($filePath, $forcar);

        $msg = "Corrigidos: {$resultado['corrigidos']}";
        if (($resultado['ja_corrigidos'] ?? 0) > 0) {
            $msg .= " | Já corrigidos (pulados): {$resultado['ja_corrigidos']}";
        }
        if ($resultado['nao_encontrados'] > 0) {
            $msg .= " | Não encontrados: {$resultado['nao_encontrados']}";
        }
        if ($resultado['erros'] > 0) {
            $msg .= " | Erros: {$resultado['erros']}";
        }

        if ($resultado['corrigidos'] > 0) {
            Notification::make()->title($msg)->success()->send();
        } else {
            Notification::make()->title($msg)->warning()->send();
        }
    }

    public function reprocessarDadosBling(): void
    {
        $this->corrigirDadosBling(forcar: true);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
