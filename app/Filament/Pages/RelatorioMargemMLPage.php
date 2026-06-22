<?php

namespace App\Filament\Pages;

use App\Models\CanalVenda;
use App\Models\RelatorioMargemML;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class RelatorioMargemMLPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Mercado Livre';
    protected static ?string $navigationLabel = 'Relatório Margem';
    protected static ?string $title = 'Relatório de Margem - Mercado Livre';
    protected static string $view = 'filament.pages.relatorio-margem-ml';
    protected static ?int $navigationSort = 30;

    public string $filtroAccount = '';
    public string $filtroCatalogo = '';
    public string $filtroListingType = '';
    public string $ordenar = 'margem_pct_asc';
    public string $busca = '';

    // Consulta em tempo real por family_id
    public string $familyIdBusca = '';
    public string $familyAccountBusca = 'primary';
    public array $familyResultados = [];
    public bool $familyBuscando = false;
    public ?string $familyJobKey = null;

    public function getAntecipacaoPctProperty(): float
    {
        return (float) (CanalVenda::where('nome_canal', 'Mercadolivre')->value('percentual_antecipacao') ?? 0);
    }

    public function getItensProperty()
    {
        $query = RelatorioMargemML::query();

        if ($this->filtroAccount) {
            $query->where('account_key', $this->filtroAccount);
        }
        if ($this->filtroCatalogo === 'sim') {
            $query->where('is_catalog_listing', true);
        } elseif ($this->filtroCatalogo === 'nao') {
            $query->where('is_catalog_listing', false);
        }
        if ($this->filtroListingType) {
            $query->where('listing_type', $this->filtroListingType);
        }
        if ($this->busca) {
            $query->where(function ($q) {
                $q->where('titulo', 'like', "%{$this->busca}%")
                  ->orWhere('sku', 'like', "%{$this->busca}%")
                  ->orWhere('mlb_id', 'like', "%{$this->busca}%");
            });
        }

        $query = match ($this->ordenar) {
            'margem_pct_asc' => $query->orderBy('margem_pct', 'asc'),
            'margem_pct_desc' => $query->orderBy('margem_pct', 'desc'),
            'preco_desc' => $query->orderBy('preco_venda', 'desc'),
            'preco_asc' => $query->orderBy('preco_venda', 'asc'),
            'margem_promo_asc' => $query->orderByRaw('COALESCE(margem_promocional_pct, 999) ASC'),
            default => $query->orderBy('margem_pct', 'asc'),
        };

        return $query->get();
    }

    public function getGeradoEmProperty(): ?string
    {
        $ultimo = RelatorioMargemML::orderByDesc('gerado_em')->first();
        return $ultimo?->gerado_em?->format('d/m/Y H:i');
    }

    public function buscarPorFamily(): void
    {
        $familyId = trim($this->familyIdBusca);
        if (empty($familyId)) {
            Notification::make()->title('Informe um Family ID.')->warning()->send();
            return;
        }

        $this->familyBuscando = true;
        $this->familyResultados = [];
        $this->familyJobKey = 'family_search_' . md5($familyId . $this->familyAccountBusca . now()->timestamp);

        \App\Jobs\BuscarFamilyMargemJob::dispatch(
            $familyId,
            $this->familyAccountBusca,
            $this->familyJobKey
        );

        Notification::make()->title('Busca iniciada... aguarde.')->info()->send();
    }

    public function verificarResultadoFamily(): void
    {
        if (!$this->familyJobKey) return;

        $resultado = cache($this->familyJobKey);
        if ($resultado === null) return;

        if (isset($resultado['erro'])) {
            Notification::make()->title($resultado['erro'])->danger()->send();
            $this->familyBuscando = false;
            $this->familyJobKey = null;
            return;
        }

        $this->familyResultados = $resultado['itens'] ?? [];
        $this->familyBuscando = false;
        cache()->forget($this->familyJobKey);
        $this->familyJobKey = null;

        Notification::make()->title(count($this->familyResultados) . ' itens encontrados.')->success()->send();
    }

    public function limparFamily(): void
    {
        $this->familyResultados = [];
        $this->familyIdBusca = '';
        $this->familyJobKey = null;
        $this->familyBuscando = false;
    }

    public function updatedFiltroAccount() {}
    public function updatedFiltroCatalogo() {}
    public function updatedFiltroListingType() {}
    public function updatedOrdenar() {}
    public function updatedBusca() {}

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'marketing']) ?? false;
    }
}
