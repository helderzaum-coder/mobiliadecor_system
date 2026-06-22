<?php

namespace App\Filament\Pages;

use App\Models\CanalVenda;
use App\Models\ImpostoMensal;
use App\Models\MercadoLivreToken;
use App\Models\RelatorioMargemML;
use App\Services\Bling\BlingClient;
use App\Services\MercadoLivre\MercadoLivreClient;
use App\Services\MercadoLivre\MercadoLivrePromotionService;
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

        try {
            $accountKey = $this->familyAccountBusca;
            $mlClient = new MercadoLivreClient($accountKey);
            $promotionService = new MercadoLivrePromotionService($accountKey);
            $blingClient = new BlingClient($accountKey);

            if (!$mlClient->isAuthorized()) {
                Notification::make()->title("Conta ML '{$accountKey}' não autorizada.")->danger()->send();
                return;
            }

            // Buscar imposto e antecipação
            $idCnpj = $accountKey === 'secondary' ? 2 : 1;
            $impostoPct = $this->getImpostoPctCalc($idCnpj);
            $antecipacaoPct = $this->antecipacao_pct;

            // Buscar itens da family via user_product
            $tokenModel = MercadoLivreToken::where('account_key', $accountKey)->first();
            $userId = $tokenModel?->user_id ?? config("mercadolivre.accounts.{$accountKey}.user_id");

            // Buscar itens pelo family_id (via search com catalog_product_id ou family)
            $result = $mlClient->get("/users/{$userId}/items/search", [
                'status' => 'active',
                'family_id' => $familyId,
                'limit' => 50,
            ]);

            $itemIds = $result['body']['results'] ?? [];

            // Se não encontrou, tentar por catalog_product_id
            if (empty($itemIds)) {
                $result = $mlClient->get("/users/{$userId}/items/search", [
                    'status' => 'active',
                    'catalog_product_id' => $familyId,
                    'limit' => 50,
                ]);
                $itemIds = $result['body']['results'] ?? [];
            }

            if (empty($itemIds)) {
                Notification::make()->title("Nenhum item encontrado para family '{$familyId}'.")->warning()->send();
                return;
            }

            $resultados = [];
            foreach ($itemIds as $itemId) {
                $itemData = $this->processarItemRealtime($mlClient, $promotionService, $blingClient, $itemId, $impostoPct, $antecipacaoPct);
                if ($itemData) {
                    $resultados[] = $itemData;
                }
            }

            $this->familyResultados = $resultados;
            Notification::make()->title(count($resultados) . " itens encontrados para family '{$familyId}'.")->success()->send();

        } catch (\Throwable $e) {
            Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
        } finally {
            $this->familyBuscando = false;
        }
    }

    private function processarItemRealtime(
        MercadoLivreClient $mlClient,
        MercadoLivrePromotionService $promotionService,
        BlingClient $blingClient,
        string $itemId,
        float $impostoPct,
        float $antecipacaoPct
    ): ?array {
        $itemResult = $mlClient->get("/items/{$itemId}");
        if (!$itemResult['success']) return null;

        $item = $itemResult['body'];
        $preco = (float) ($item['price'] ?? 0);
        $estoque = (int) ($item['available_quantity'] ?? 0);
        $titulo = $item['title'] ?? '';
        $listingType = $item['listing_type_id'] ?? '';
        $categoryId = $item['category_id'] ?? '';

        if ($preco <= 0) return null;

        // SKU
        $sku = $item['seller_custom_field'] ?? null;
        if (!$sku) {
            foreach ($item['attributes'] ?? [] as $attr) {
                if (($attr['id'] ?? '') === 'SELLER_SKU') {
                    $sku = $attr['value_name'] ?? null;
                    break;
                }
            }
        }
        if (!$sku && !empty($item['variations'])) {
            $sku = $item['variations'][0]['seller_custom_field'] ?? null;
        }
        if ($sku && str_contains($sku, '__')) {
            $sku = explode('__', $sku)[0];
        }

        // Custo
        $custo = 0;
        if ($sku) {
            try {
                $produto = $blingClient->getProductBySku($sku);
                $custo = (float) ($produto['precoCusto'] ?? 0);
                if ($custo <= 0 && !empty($produto['id'])) {
                    $detalhe = $blingClient->getProductById((int) $produto['id']);
                    $custo = (float) ($detalhe['precoCusto'] ?? 0);
                }
            } catch (\Throwable) {}
        }

        // Comissão
        $comissaoData = $promotionService->buscarComissaoParaPreco(
            $preco, $listingType, $categoryId,
            $item['shipping']['logistic_type'] ?? 'xd_drop_off',
            $item['shipping']['mode'] ?? 'me2'
        );
        $comissaoPct = $comissaoData['percent'] ?? ($listingType === 'gold_pro' ? 16.5 : 11.5);
        $comissaoValor = $comissaoData['valor'] ?? round($preco * $comissaoPct / 100, 2);

        // Frete
        $frete = 0;
        $freeShipping = $item['shipping']['free_shipping'] ?? false;
        if ($freeShipping) {
            $freteResult = $mlClient->get("/items/{$itemId}/shipping_options", ['zip_code' => '01310100']);
            if ($freteResult['success'] && !empty($freteResult['body']['options'])) {
                $frete = round((float) ($freteResult['body']['options'][0]['list_cost'] ?? 0), 2);
            }
        }

        // Cálculos
        $impostoValor = round($preco * $impostoPct / 100, 2);
        $antecipacaoValor = round($preco * $antecipacaoPct / 100, 2);
        $margemValor = round($preco - $comissaoValor - $frete - $antecipacaoValor - $impostoValor - $custo, 2);
        $margemPct = $preco > 0 ? round(($margemValor / $preco) * 100, 2) : 0;

        // Promoções
        $promoResult = $promotionService->buscarPromocoesParaItem($itemId);
        $promocoes = [];
        if ($promoResult['success'] && !empty($promoResult['promotions'])) {
            foreach ($promoResult['promotions'] as $promo) {
                $pp = (float) ($promo['price'] ?? 0);
                if ($pp <= 0) $pp = (float) ($promo['max_discounted_price'] ?? 0);
                if ($pp <= 0) $pp = (float) ($promo['suggested_discounted_price'] ?? 0);
                if ($pp <= 0) continue;

                $comPromo = $promotionService->buscarComissaoParaPreco(
                    $pp, $listingType, $categoryId,
                    $item['shipping']['logistic_type'] ?? 'xd_drop_off',
                    $item['shipping']['mode'] ?? 'me2'
                );
                $comPromoValor = $comPromo['valor'] ?? round($pp * $comissaoPct / 100, 2);
                $impPromo = round($pp * $impostoPct / 100, 2);
                $antPromo = round($pp * $antecipacaoPct / 100, 2);
                $rebatePromo = round($pp * (float) ($promo['meli_percentage'] ?? 0) / 100, 2);
                $promoMargem = round($pp - $comPromoValor - $frete - $antPromo - $impPromo - $custo + $rebatePromo, 2);
                $promoMargemPct = round(($promoMargem / $pp) * 100, 2);

                $promocoes[] = [
                    'nome' => $promo['name'] ?? 'Sem nome',
                    'tipo' => $promo['type'] ?? '',
                    'status' => $promo['status'] ?? '',
                    'preco' => $pp,
                    'meli_pct' => $promo['meli_percentage'] ?? 0,
                    'rebate_valor' => $rebatePromo,
                    'margem_valor' => $promoMargem,
                    'margem_pct' => $promoMargemPct,
                ];
            }
        }

        return [
            'mlb_id' => $itemId,
            'sku' => $sku,
            'titulo' => $titulo,
            'listing_type' => $listingType,
            'preco_venda' => $preco,
            'custo_produto' => $custo,
            'comissao_pct' => $comissaoPct,
            'comissao_valor' => $comissaoValor,
            'frete' => $frete,
            'free_shipping' => $freeShipping,
            'imposto_pct' => $impostoPct,
            'imposto_valor' => $impostoValor,
            'antecipacao_valor' => $antecipacaoValor,
            'margem_valor' => $margemValor,
            'margem_pct' => $margemPct,
            'estoque' => $estoque,
            'promocoes' => $promocoes,
        ];
    }

    private function getImpostoPctCalc(int $idCnpj): float
    {
        $imposto = ImpostoMensal::where('id_cnpj', $idCnpj)
            ->where('mes_referencia', now()->month)
            ->where('ano_referencia', now()->year)
            ->first();

        if (!$imposto) {
            $imposto = ImpostoMensal::where('id_cnpj', $idCnpj)
                ->orderByDesc('ano_referencia')
                ->orderByDesc('mes_referencia')
                ->first();
        }

        return (float) ($imposto->percentual_imposto ?? 0);
    }

    public function limparFamily(): void
    {
        $this->familyResultados = [];
        $this->familyIdBusca = '';
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
