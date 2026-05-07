<?php

namespace App\Filament\Pages;

use App\Services\ShopeeAfiliadosService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportarShopeeAfiliados extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Afiliados Shopee';
    protected static ?string $title = 'Importar Comissão de Afiliados Shopee';
    protected static string $view = 'filament.pages.importar-shopee-afiliados';

    public ?array $data = [];
    public ?string $data_inicio_marcar = null;
    public ?string $data_fim_marcar = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('arquivo')
                ->label('Planilha de Afiliados (.csv)')
                ->acceptedFileTypes(['text/csv', 'application/octet-stream', '.csv'])
                ->required()
                ->directory('shopee-planilhas')
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

        $resultado = ShopeeAfiliadosService::processar($filePath);

        $msg = "Atualizados: {$resultado['atualizados']}";
        if ($resultado['nao_encontrados'] > 0) $msg .= " | Não encontrados: {$resultado['nao_encontrados']}";
        if ($resultado['erros'] > 0) $msg .= " | Erros: {$resultado['erros']}";

        if ($resultado['atualizados'] > 0) {
            Notification::make()->title($msg)->success()->send();
        } else {
            Notification::make()->title($msg)->warning()->send();
        }

        $this->data = [];
        $this->form->fill();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function marcarPeriodo(): void
    {
        if (!$this->data_inicio_marcar || !$this->data_fim_marcar) {
            Notification::make()->title('Informe o período.')->warning()->send();
            return;
        }

        $atualizados = \App\Models\Venda::where('planilha_afiliado_processada', false)
            ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%'))
            ->whereBetween('data_venda', [$this->data_inicio_marcar, $this->data_fim_marcar])
            ->update(['planilha_afiliado_processada' => true]);

        Notification::make()->title("{$atualizados} venda(s) Shopee marcadas como processadas (sem afiliado).")->success()->send();
    }
}
