<?php

namespace App\Jobs;

use App\Models\CanalVenda;
use App\Models\ImpostoMensal;
use App\Models\MercadoLivreToken;
use App\Services\Bling\BlingClient;
use App\Services\MercadoLivre\MercadoLivreClient;
use App\Services\MercadoLivre\MercadoLivrePromotionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BuscarFamilyMargemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        private readonly string $familyId,
        private readonly string $accountKey,
        private readonly string $cacheKey
    ) {}

    public function handle(): void
    {
        try {
            $resultado = $this->executar();
            Cache::put($this->cacheKey, $resultado, 300);
        } catch (\Throwable $e) {
            Cache::put($this->cacheKey, ['erro' => 'Erro: ' . $e->getMessage()], 300);
            Log::error("BuscarFamilyMargemJob: {$e->getMessage()}");
        }
    }

    private function executar(): array
    {
        $mlClient = new MercadoLivreClient($this->accountKey);
        $promotionService = new MercadoLivrePromotionService($this->accountKey);
        $blingClient = new BlingClient($this->accountKey);

        if (!$mlClient->isAuthorized()) {
            return ['erro' => "Conta ML '{$this->accountKey}' não autorizada."];
        }

        $idCnpj = $this->accountKey === 'secondary' ? 2 : 1;
        $impostoPct = $this->getImpostoPct($idCnpj);
        $antecipacaoPct = (float) (CanalVenda::where('nome_canal', 'Mercadolivre')->value('percentual_antecipacao') ?? 0);

        $tokenModel = MercadoLivreToken::where('account_key', $this->accountKey)->first();
        $userId = $tokenModel?->user_id ?? config("mercadolivre.accounts.{$this->accountKey}.user_id");

        // 1) Buscar itens via user_products/search por family_id
        $itemIds = [];

        // Tentar buscar user_products desta family
        $upResult = $mlClient->get("/users/{$userId}/user_products/search", [
            'family_id' => $this->familyId,
            'status' => 'active',
            'limit' => 50,
        ]);

        if ($upResult['success'] && !empty($upResult['body']['results'])) {
            // Cada user_product pode ter items vinculados
            foreach ($upResult['body']['results'] as $up) {
                $upId = $up['id'] ?? $up;
                $upDetailResult = $mlClient->get("/user-products/{$upId}");
                if ($upDetailResult['success'] && !empty($upDetailResult['body']['items'])) {
                    foreach ($upDetailResult['body']['items'] as $itemRef) {
                        $itemIds[] = $itemRef['id'] ?? $itemRef;
                    }
                }
            }
        }

        // Fallback: buscar por catalog_product_id (que pode ser o family_id informado)
        if (empty($itemIds)) {
            $searchResult = $mlClient->get("/users/{$userId}/items/search", [
                'status' => 'active',
                'catalog_product_id' => $this->familyId,
                'limit' => 50,
            ]);
            $itemIds = $searchResult['body']['results'] ?? [];
        }

        // Fallback 2: buscar itens e filtrar pelo family_id/catalog_product_id no detalhe
        if (empty($itemIds)) {
            $searchResult = $mlClient->get("/users/{$userId}/items/search", [
                'status' => 'active',
                'limit' => 50,
            ]);
            $allIds = $searchResult['body']['results'] ?? [];

            if (!empty($allIds)) {
                // Multiget e filtrar
                foreach (array_chunk($allIds, 20) as $chunk) {
                    $ids = implode(',', $chunk);
                    $multiResult = $mlClient->get('/items', ['ids' => $ids]);
                    if ($multiResult['success'] && is_array($multiResult['body'])) {
                        foreach ($multiResult['body'] as $entry) {
                            if (($entry['code'] ?? 0) != 200 || empty($entry['body'])) continue;
                            $body = $entry['body'];
                            $catalogId = $body['catalog_product_id'] ?? '';
                            $userProductId = $body['user_product_id'] ?? '';
                            if ($catalogId === $this->familyId || $userProductId === $this->familyId) {
                                $itemIds[] = $body['id'];
                            }
                        }
                    }
                }
            }
        }

        if (empty($itemIds)) {
            return ['erro' => "Nenhum item encontrado para '{$this->familyId}'."];
        }

        $itemIds = array_unique($itemIds);

        // 2) Multiget dos itens
        $allItems = [];
        foreach (array_chunk($itemIds, 20) as $chunk) {
            $ids = implode(',', $chunk);
            $multiResult = $mlClient->get('/items', ['ids' => $ids]);
            if ($multiResult['success'] && is_array($multiResult['body'])) {
                foreach ($multiResult['body'] as $entry) {
                    if (($entry['code'] ?? 0) == 200 && !empty($entry['body'])) {
                        $allItems[] = $entry['body'];
                    }
                }
            }
        }

        if (empty($allItems)) {
            return ['erro' => 'Não foi possível obter dados dos itens.'];
        }

        // 3) SKUs e custos (deduplica)
        $skuMap = [];
        foreach ($allItems as $item) {
            $sku = $this->extrairSku($item);
            if ($sku) $skuMap[$item['id']] = $sku;
        }

        $custos = [];
        foreach (array_unique(array_values($skuMap)) as $sku) {
            try {
                $produto = $blingClient->getProductBySku($sku);
                $custo = (float) ($produto['precoCusto'] ?? 0);
                if ($custo <= 0 && !empty($produto['id'])) {
                    $detalhe = $blingClient->getProductById((int) $produto['id']);
                    $custo = (float) ($detalhe['precoCusto'] ?? 0);
                }
                $custos[$sku] = $custo;
            } catch (\Throwable) {
                $custos[$sku] = 0;
            }
        }

        // 4) Frete (só free_shipping)
        $fretes = [];
        foreach ($allItems as $item) {
            if ($item['shipping']['free_shipping'] ?? false) {
                $freteResult = $mlClient->get("/items/{$item['id']}/shipping_options", ['zip_code' => '01310100']);
                $fretes[$item['id']] = ($freteResult['success'] && !empty($freteResult['body']['options']))
                    ? round((float) ($freteResult['body']['options'][0]['list_cost'] ?? 0), 2)
                    : 0;
            } else {
                $fretes[$item['id']] = 0;
            }
        }

        // 5) Montar resultados
        $resultados = [];
        foreach ($allItems as $item) {
            $preco = (float) ($item['price'] ?? 0);
            if ($preco <= 0) continue;

            $itemId = $item['id'];
            $sku = $skuMap[$itemId] ?? null;
            $custo = $sku ? ($custos[$sku] ?? 0) : 0;
            $frete = $fretes[$itemId] ?? 0;
            $listingType = $item['listing_type_id'] ?? '';
            $categoryId = $item['category_id'] ?? '';

            $comissaoData = $promotionService->buscarComissaoParaPreco(
                $preco, $listingType, $categoryId,
                $item['shipping']['logistic_type'] ?? 'xd_drop_off',
                $item['shipping']['mode'] ?? 'me2'
            );
            $comissaoPct = $comissaoData['percent'] ?? ($listingType === 'gold_pro' ? 16.5 : 11.5);
            $comissaoValor = $comissaoData['valor'] ?? round($preco * $comissaoPct / 100, 2);

            $impostoValor = round($preco * $impostoPct / 100, 2);
            $antecipacaoValor = round($preco * $antecipacaoPct / 100, 2);
            $margemValor = round($preco - $comissaoValor - $frete - $antecipacaoValor - $impostoValor - $custo, 2);
            $margemPct = round(($margemValor / $preco) * 100, 2);

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

            $resultados[] = [
                'mlb_id' => $itemId,
                'sku' => $sku,
                'titulo' => $item['title'] ?? '',
                'listing_type' => $listingType,
                'preco_venda' => $preco,
                'custo_produto' => $custo,
                'comissao_pct' => $comissaoPct,
                'comissao_valor' => $comissaoValor,
                'frete' => $frete,
                'free_shipping' => $item['shipping']['free_shipping'] ?? false,
                'imposto_pct' => $impostoPct,
                'imposto_valor' => $impostoValor,
                'antecipacao_valor' => $antecipacaoValor,
                'margem_valor' => $margemValor,
                'margem_pct' => $margemPct,
                'estoque' => (int) ($item['available_quantity'] ?? 0),
                'promocoes' => $promocoes,
            ];
        }

        return ['itens' => $resultados];
    }

    private function extrairSku(array $item): ?string
    {
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
        return $sku;
    }

    private function getImpostoPct(int $idCnpj): float
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
}
