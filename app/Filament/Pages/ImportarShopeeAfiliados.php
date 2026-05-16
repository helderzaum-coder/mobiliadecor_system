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
    public ?string $data_inicio_periodo = null;
    public ?string $data_fim_periodo = null;

    public function mount(): void
    {
        $this->data_inicio_periodo = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->data_fim_periodo = now()->subMonth()->endOfMonth()->format('Y-m-d');
    }

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

    /**
     * Retorna a data do primeiro pedido Shopee pendente de afiliado.
     */
    public function getDataPrimeiroPendenteProperty(): ?string
    {
        $venda = \App\Models\Venda::where('planilha_afiliado_processada', false)
            ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%'))
            ->orderBy('data_venda', 'asc')
            ->value('data_venda');

        return $venda ? \Carbon\Carbon::parse($venda)->format('d/m/Y') : null;
    }

    /**
     * Retorna quantos pedidos Shopee do período ainda não foram processados para afiliado.
     */
    public function getPedidosPendentesProperty(): int
    {
        if (!$this->data_inicio_periodo || !$this->data_fim_periodo) return 0;

        return \App\Models\Venda::where('planilha_afiliado_processada', false)
            ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%'))
            ->whereBetween('data_venda', [$this->data_inicio_periodo, $this->data_fim_periodo])
            ->count();
    }

    /**
     * Retorna quantos pedidos anteriores ao período ainda estão pendentes.
     */
    public function getPendentesAnterioresProperty(): int
    {
        if (!$this->data_inicio_periodo) return 0;

        return \App\Models\Venda::where('planilha_afiliado_processada', false)
            ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%'))
            ->where('data_venda', '<', $this->data_inicio_periodo)
            ->count();
    }

    public function processar(): void
    {
        if (!$this->data_inicio_periodo || !$this->data_fim_periodo) {
            Notification::make()->title('Informe o período da importação.')->danger()->send();
            return;
        }

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

        // 1) Travar pedidos anteriores ao período (marcar como processados sem afiliado)
        $travados = \App\Models\Venda::where('planilha_afiliado_processada', false)
            ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%'))
            ->where('data_venda', '<', $this->data_inicio_periodo)
            ->update(['planilha_afiliado_processada' => true]);

        // 2) Processar planilha (aplica comissão nos pedidos encontrados)
        $resultado = ShopeeAfiliadosService::processar($filePath);

        // 3) Marcar restantes do período como processados (sem afiliado)
        $restantes = \App\Models\Venda::where('planilha_afiliado_processada', false)
            ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%'))
            ->whereBetween('data_venda', [$this->data_inicio_periodo, $this->data_fim_periodo])
            ->update(['planilha_afiliado_processada' => true]);

        $msg = "Afiliados: {$resultado['atualizados']}";
        if ($travados > 0) $msg .= " | Travados (anteriores): {$travados}";
        if ($restantes > 0) $msg .= " | Sem afiliado (período): {$restantes}";
        if ($resultado['nao_encontrados'] > 0) $msg .= " | Não encontrados: {$resultado['nao_encontrados']}";
        if ($resultado['erros'] > 0) $msg .= " | Erros: {$resultado['erros']}";

        if ($resultado['atualizados'] > 0 || $travados > 0 || $restantes > 0) {
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
