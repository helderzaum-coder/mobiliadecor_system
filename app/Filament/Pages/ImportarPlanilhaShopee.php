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
    protected static ?string $navigationGroup = 'Planilhas';
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

    /**
     * Resolve o caminho do arquivo e valida cabeçalho.
     * Retorna o filePath ou null (com notificação de erro).
     */
    private function resolverArquivoComValidacao(): ?string
    {
        try {
            $data = $this->form->getState();
        } catch (\Exception $e) {
            $this->data = [];
            $this->form->fill();
            Notification::make()->title('O arquivo enviado expirou. Faça o upload novamente.')->danger()->send();
            return null;
        }

        $arquivo = $data['arquivo'] ?? null;
        if (!$arquivo) {
            Notification::make()->title('Selecione um arquivo.')->danger()->send();
            return null;
        }

        $filePath = storage_path('app/public/' . $arquivo);
        if (!file_exists($filePath)) {
            $filePath = storage_path('app/' . $arquivo);
        }
        if (!file_exists($filePath)) {
            Notification::make()->title('Arquivo não encontrado.')->danger()->send();
            return null;
        }

        // Validar cabeçalho
        $validacao = ShopeePlanilhaService::validarCabecalho($filePath);
        if (!$validacao['valido']) {
            $detalhes = implode("\n", $validacao['divergencias']);
            Notification::make()
                ->title('⚠ Formato da planilha divergente — colunas precisam ser remapeadas')
                ->body($detalhes)
                ->danger()
                ->persistent()
                ->send();
            return null;
        }

        return $filePath;
    }

    public function processar(): void
    {
        $filePath = $this->resolverArquivoComValidacao();
        if (!$filePath) return;

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
        $filePath = $this->resolverArquivoComValidacao();
        if (!$filePath) return;

        $resultado = \App\Services\ShopeeCorrigirDadosService::processar($filePath, $forcar);

        $msg = "Corrigidos: {$resultado['corrigidos']}";
        if (($resultado['ja_corrigidos'] ?? 0) > 0) {
            $msg .= " | Já corrigidos (pulados): {$resultado['ja_corrigidos']}";
        }
        if ($resultado['nao_encontrados'] > 0) {
            $msg .= " | Não encontrados no staging: {$resultado['nao_encontrados']}";
        }
        if ($resultado['erros'] > 0) {
            $msg .= " | Erros: {$resultado['erros']}";
        }

        if ($resultado['corrigidos'] > 0) {
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
    }

    public function reprocessarDadosBling(): void
    {
        $this->corrigirDadosBling(forcar: true);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'operador']) ?? false;
    }
}
