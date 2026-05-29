<?php

namespace App\Services\MercadoLivre;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoLivrePromotionService
{
    private MercadoLivreOAuthService $oauth;
    private string $accountKey;
    private string $apiBase;

    public function __construct(string $accountKey)
    {
        $this->accountKey = $accountKey;
        $this->oauth = new MercadoLivreOAuthService($accountKey);
        $this->apiBase = rtrim(config('mercadolivre.api_base'), '/');
    }

    public function listarPromocoes(): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Token não disponível'];

        $tokenModel = \App\Models\MercadoLivreToken::where('account_key', $this->accountKey)->first();
        $userId = $tokenModel?->user_id ?? config("mercadolivre.accounts.{$this->accountKey}.user_id");

        if (!$userId) return ['success' => false, 'error' => 'User ID não configurado'];

        try {
            $response = Http::withToken($token)
                ->withOptions(['verify' => false])
                ->timeout(30)
                ->get("{$this->apiBase}/seller-promotions/users/{$userId}", ['app_version' => 'v2']);
        } catch (\Throwable $e) {
            Log::error("ML Promoções [{$this->accountKey}]: Timeout", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Timeout: ' . $e->getMessage()];
        }

        if ($response->failed()) {
            Log::error("ML Promoções [{$this->accountKey}]: Erro ao listar", ['status' => $response->status()]);
            return ['success' => false, 'error' => "HTTP {$response->status()}"];
        }

        $data = $response->json();
        return [
            'success' => true,
            'promotions' => $data['results'] ?? [],
        ];
    }

    public function listarItensDaPromocao(string $promotionId, string $promotionType, ?string $searchAfter = null): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Token não disponível'];

        $params = [
            'promotion_type' => $promotionType,
            'app_version' => 'v2',
            'limit' => 50,
        ];
        if ($searchAfter) {
            $params['search_after'] = $searchAfter;
        }

        try {
            $response = Http::withToken($token)
                ->withOptions(['verify' => false])
                ->timeout(60)
                ->get("{$this->apiBase}/seller-promotions/promotions/{$promotionId}/items", $params);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Timeout: ' . $e->getMessage()];
        }

        if ($response->failed()) {
            return ['success' => false, 'error' => "HTTP {$response->status()}"];
        }

        $data = $response->json();
        $items = [];

        foreach ($data['results'] ?? [] as $item) {
            $titulo = $this->extrairTitulo($item, $token);
            $preco = $item['price'] ?? null;
            $originalPrice = $item['original_price'] ?? $preco;
            $dealPrice = $item['deal_price'] ?? null;
            $proposedDealPrice = $item['proposed_deal_price'] ?? null;
            $netProceeds = $this->extrairNetProceedsAmount($item['net_proceeds'] ?? null);

            $items[] = [
                'id' => $item['id'] ?? '',
                'title' => $titulo,
                'status' => $item['status'] ?? '',
                'price' => $preco,
                'original_price' => $originalPrice,
                'deal_price' => $dealPrice ?? $proposedDealPrice,
                'proposed_deal_price' => $proposedDealPrice,
                'buyer_price' => $preco, // preço que o comprador paga (base para comissão)
                'meli_percentage' => (float) ($item['meli_percentage'] ?? 0),
                'seller_percentage' => (float) ($item['seller_percentage'] ?? 0),
                'net_proceeds' => $item['net_proceeds'] ?? null,
                'net_proceeds_amount' => $netProceeds,
                'offer_id' => $item['offer_id'] ?? $item['ref_id'] ?? $item['deal_id'] ?? $item['candidate_id'] ?? null,
            ];
        }

        $paging = $data['paging'] ?? [];
        $nextSearchAfter = $paging['search_after'] ?? $paging['searchAfter'] ?? null;

        return [
            'success' => true,
            'items' => $items,
            'total' => $paging['total'] ?? count($items),
            'search_after' => $nextSearchAfter,
        ];
    }

    public function listarTodosItensDaPromocao(string $promotionId, string $promotionType): array
    {
        $allItems = [];
        $searchAfter = null;
        $maxPages = 20;

        for ($i = 0; $i < $maxPages; $i++) {
            $result = $this->listarItensDaPromocao($promotionId, $promotionType, $searchAfter);
            if (!$result['success'] || empty($result['items'])) break;

            $allItems = array_merge($allItems, $result['items']);
            $searchAfter = $result['search_after'] ?? null;
            if (!$searchAfter) break;
        }

        return ['success' => true, 'items' => $allItems, 'total' => count($allItems)];
    }

    public function removerItemDaPromocao(string $itemId, string $promotionId, string $promotionType): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Token não disponível'];

        $response = Http::withToken($token)
            ->withOptions(['verify' => false])
            ->timeout(15)
            ->delete("{$this->apiBase}/seller-promotions/items/{$itemId}", [
                'promotion_type' => $promotionType,
                'promotion_id' => $promotionId,
                'app_version' => 'v2',
            ]);

        if ($response->successful()) {
            return ['success' => true];
        }

        // Fallback: tentar PUT com deal_price = 0
        if ($response->status() === 403) {
            $putResponse = Http::withToken($token)
                ->withOptions(['verify' => false])
                ->timeout(15)
                ->put("{$this->apiBase}/seller-promotions/items/{$itemId}?app_version=v2", [
                    'deal_price' => 0,
                    'promotion_id' => $promotionId,
                    'promotion_type' => $promotionType,
                ]);

            if ($putResponse->successful()) {
                return ['success' => true];
            }

            return ['success' => false, 'error' => "PUT falhou: HTTP {$putResponse->status()}"];
        }

        if ($response->status() === 404) {
            return ['success' => true, 'message' => 'Item já não está na promoção'];
        }

        return ['success' => false, 'error' => "HTTP {$response->status()}: " . ($response->json()['message'] ?? '')];
    }

    public function aderirPromocao(string $itemId, string $promotionId, string $promotionType, float $dealPrice, ?string $offerId = null, ?string $startDate = null, ?string $finishDate = null): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Token não disponível'];

        // SMART: usa offer_id, sem deal_price
        if ($promotionType === 'SMART' && $offerId) {
            $body = [
                'promotion_id' => $promotionId,
                'promotion_type' => $promotionType,
                'offer_id' => $offerId,
            ];
        } else {
            $body = [
                'promotion_type' => $promotionType,
                'promotion_id' => $promotionId,
                'deal_price' => $dealPrice,
            ];
            if ($offerId) {
                $body['offer_id'] = $offerId;
            }
            if ($startDate) {
                $body['start_date'] = $startDate;
            }
            if ($finishDate) {
                $body['finish_date'] = $finishDate;
            }
        }

        try {
            $response = Http::withToken($token)
                ->withOptions(['verify' => false])
                ->timeout(15)
                ->post("{$this->apiBase}/seller-promotions/items/{$itemId}?app_version=v2", $body);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Timeout: ' . $e->getMessage()];
        }

        if ($response->successful()) {
            return ['success' => true];
        }

        $respBody = $response->json();
        return ['success' => false, 'error' => "HTTP {$response->status()}: " . ($respBody['message'] ?? $response->body())];
    }

    public function editarPrecoPromocional(string $itemId, string $promotionId, string $promotionType, float $dealPrice): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Token não disponível'];

        $response = Http::withToken($token)
            ->withOptions(['verify' => false])
            ->timeout(15)
            ->put("{$this->apiBase}/seller-promotions/items/{$itemId}?app_version=v2", [
                'deal_price' => $dealPrice,
                'promotion_id' => $promotionId,
                'promotion_type' => $promotionType,
            ]);

        if ($response->successful()) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => "HTTP {$response->status()}: " . ($response->json()['message'] ?? $response->body())];
    }

    private function extrairTitulo(array $item, string $token): string
    {
        // 1. item_details_raw.title
        if (!empty($item['item_details_raw']['title'])) {
            return $item['item_details_raw']['title'];
        }
        // 2. title direto
        if (!empty($item['title'])) {
            return $item['title'];
        }
        // 3. name
        if (!empty($item['name'])) {
            return $item['name'];
        }
        return '';
    }

    private function extrairNetProceedsAmount(mixed $netProceeds): ?float
    {
        if (is_numeric($netProceeds)) {
            return (float) $netProceeds;
        }

        if (!is_array($netProceeds)) {
            return null;
        }

        foreach (['amount', 'value'] as $key) {
            if (isset($netProceeds[$key]) && is_numeric($netProceeds[$key])) {
                return (float) $netProceeds[$key];
            }
        }

        foreach (['suggested_discounted_price', 'max_discounted_price', 'min_discounted_price'] as $key) {
            if (!isset($netProceeds[$key]) || !is_array($netProceeds[$key])) {
                continue;
            }

            foreach (['amount', 'value'] as $amountKey) {
                if (isset($netProceeds[$key][$amountKey]) && is_numeric($netProceeds[$key][$amountKey])) {
                    return (float) $netProceeds[$key][$amountKey];
                }
            }
        }

        return null;
    }

    /**
     * Busca títulos em batch via multiget (até 20 por chamada)
     */
    public function buscarInfoParaAdesao(string $itemId): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return [];

        $info = ['base_price' => 0, 'frete' => 0, 'comissao_percent' => 0, 'comissao_valor' => 0, 'listing_type' => '', 'sku' => null, 'custo_produto' => 0, 'title' => null];

        try {
            $resp = Http::withToken($token)->withOptions(['verify' => false])->timeout(10)
                ->get("{$this->apiBase}/items/{$itemId}");

            if ($resp->status() === 403 || $resp->status() === 404) {
                Log::warning("ML buscarInfoParaAdesao [{$itemId}]: item inacessível (HTTP {$resp->status()}). Tentando dados alternativos.");
                // Item inacessível - tentar buscar custo pelo SKU se disponível via promoção
                $info['erro_api'] = true;
                return $info;
            }

            if ($resp->successful()) {
                $item = $resp->json();
                $info['base_price'] = $item['base_price'] ?? $item['price'] ?? 0;
                $info['listing_type'] = $item['listing_type_id'] ?? '';
                $info['title'] = $item['title'] ?? null;

                // Comissão real via API listing_prices (valor absoluto)
                $categoryId = $item['category_id'] ?? '';
                $listingType = $item['listing_type_id'] ?? '';
                $logisticType = $item['shipping']['logistic_type'] ?? 'xd_drop_off';
                $shippingMode = $item['shipping']['mode'] ?? 'me2';
                $price = $info['base_price'];
                $comissaoData = $this->buscarComissaoReal($token, $price, $listingType, $categoryId, $logisticType, $shippingMode);
                $info['comissao_percent'] = $comissaoData['percent'];
                $info['comissao_valor'] = $comissaoData['valor'];

                // Log completo para debug
                Log::info("ML buscarInfoParaAdesao [{$itemId}]: dados completos", [
                    'base_price' => $info['base_price'],
                    'listing_type' => $listingType,
                    'category_id' => $categoryId,
                    'logistic_type' => $logisticType,
                    'shipping_mode' => $shippingMode,
                    'comissao_percent' => $comissaoData['percent'],
                    'comissao_valor' => $comissaoData['valor'],
                    'free_shipping' => $item['shipping']['free_shipping'] ?? false,
                    'shipping_tags' => $item['shipping']['tags'] ?? [],
                ]);

                // SKU via seller_custom_field, attributes ou variations
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
                // Tratar SKUs compostos: "10061880__4286723" → "10061880"
                if ($sku && str_contains($sku, '__')) {
                    $sku = explode('__', $sku)[0];
                }
                $info['sku'] = $sku;

                // Custo de compra via Bling
                if ($sku) {
                    try {
                        $bling = new \App\Services\Bling\BlingClient($this->accountKey);
                        $produto = $bling->getProductBySku($sku);
                        $custo = (float) ($produto['precoCusto'] ?? 0);
                        // Se listagem retornou 0, tentar no detalhe
                        if ($custo <= 0 && !empty($produto['id'])) {
                            $detalhe = $bling->getProductById((int) $produto['id']);
                            $custo = (float) ($detalhe['precoCusto'] ?? 0);
                        }
                        $info['custo_produto'] = $custo;
                    } catch (\Throwable $e) {
                        Log::warning("ML buscarInfoParaAdesao [{$itemId}]: erro Bling SKU {$sku}: " . $e->getMessage());
                    }
                }

                // Buscar custo real do vendedor via shipping_options.
                $freteResp = Http::withToken($token)->withOptions(['verify' => false])->timeout(10)
                    ->get("{$this->apiBase}/items/{$itemId}/shipping_options", ['zip_code' => '01310100']);

                if ($freteResp->successful()) {
                    $options = $freteResp->json()['options'] ?? [];
                        // Pegar o maior custo entre as opções (geralmente a primeira é a mais cara)
                    $maiorFrete = 0;
                    foreach ($options as $opt) {
                        $listCost = (float) ($opt['list_cost'] ?? 0);
                        $buyerCost = (float) ($opt['cost'] ?? 0);
                        $cost = max(0, $listCost - $buyerCost);
                        if ($cost <= 0 && !empty($opt['free_shipping'])) {
                            $cost = $listCost;
                        }
                        $maiorFrete = max($maiorFrete, $cost);
                    }
                    $info['frete'] = $maiorFrete;
                    }
            }
        } catch (\Throwable $e) {
            Log::warning("ML buscarInfoParaAdesao [{$itemId}]: " . $e->getMessage());
        }

        return $info;
    }

    private function buscarComissaoReal(string $token, float $price, string $listingType, string $categoryId, string $logisticType = 'xd_drop_off', string $shippingMode = 'me2'): array
    {
        $fallback = ['percent' => $this->percentualComissao($listingType), 'valor' => 0];

        if (!$price || !$listingType || !$categoryId) {
            return $fallback;
        }

        try {
            $params = [
                'price' => $price,
                'listing_type_id' => $listingType,
                'category_id' => $categoryId,
                'logistic_type' => $logisticType,
                'shipping_mode' => $shippingMode,
            ];

            $resp = Http::withToken($token)->withOptions(['verify' => false])->timeout(10)
                ->get("{$this->apiBase}/sites/MLB/listing_prices", $params);

            if ($resp->successful()) {
                $data = $resp->json();
                // Resposta pode ser array ou objeto único
                $item = is_array($data) && isset($data[0]) ? collect($data)->firstWhere('listing_type_id', $listingType) ?? $data[0] : $data;
                $saleFee = $item['sale_fee_details'] ?? [];
                $percent = (float) ($saleFee['percentage_fee'] ?? 0);
                $valor = (float) ($item['sale_fee_amount'] ?? 0);

                if ($percent > 0 || $valor > 0) {
                    return ['percent' => $percent ?: ($price > 0 ? ($valor / $price) * 100 : 0), 'valor' => $valor];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("ML buscarComissaoReal: " . $e->getMessage());
        }

        return $fallback;
    }

    private function percentualComissao(?string $listingTypeId): float
    {
        return match ($listingTypeId) {
            'gold_special' => 11.5,
            'gold_pro' => 16.5,
            default => 11.5,
        };
    }

    public function buscarOfferIdDoItem(string $itemId, string $promotionId, string $promotionType): ?string
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return null;

        $searchAfter = null;
        $maxPages = 20;
        $itemIdNorm = strtoupper($itemId);

        for ($i = 0; $i < $maxPages; $i++) {
            $params = [
                'promotion_type' => $promotionType,
                'app_version' => 'v2',
                'limit' => 50,
            ];
            if ($searchAfter) {
                $params['search_after'] = $searchAfter;
            }

            try {
                $url = "{$this->apiBase}/seller-promotions/promotions/{$promotionId}/items";
                Log::info("ML buscarOfferIdDoItem: GET {$url}", ['params' => $params]);

                $resp = Http::withToken($token)->withOptions(['verify' => false])->timeout(30)
                    ->get($url, $params);

                if (!$resp->successful()) {
                    Log::warning("ML buscarOfferIdDoItem: HTTP {$resp->status()}", ['body' => $resp->body()]);
                    break;
                }

                $data = $resp->json();
                $results = $data['results'] ?? [];

                // Log primeira página para debug
                if ($i === 0) {
                    $ids = array_map(fn($r) => $r['id'] ?? '?', array_slice($results, 0, 5));
                    Log::info("ML buscarOfferIdDoItem [{$itemId}]: promo {$promotionId}, primeiros IDs: " . implode(', ', $ids) . " (total pág: " . count($results) . ")");
                }

                foreach ($results as $item) {
                    $id = strtoupper($item['id'] ?? '');
                    if ($id === $itemIdNorm) {
                        $offerId = $item['offer_id'] ?? $item['deal_id'] ?? $item['candidate_id'] ?? $item['promotion_item_id'] ?? null;
                        if (!$offerId) {
                            Log::warning("ML buscarOfferIdDoItem [{$itemId}]: encontrado mas sem offer_id", ['keys' => array_keys($item)]);
                        }
                        return $offerId;
                    }
                }

                $paging = $data['paging'] ?? [];
                $searchAfter = $paging['search_after'] ?? $paging['searchAfter'] ?? null;
                if (!$searchAfter) break;
            } catch (\Throwable $e) {
                break;
            }
        }

        Log::warning("ML buscarOfferIdDoItem [{$itemId}]: item NÃO encontrado na promoção {$promotionId} após {$i} páginas");
        return null;
    }

    public function buscarPromocoesParaItem(string $itemId): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Token não disponível'];

        try {
            $response = Http::withToken($token)
                ->withOptions(['verify' => false])
                ->timeout(15)
                ->get("{$this->apiBase}/seller-promotions/items/{$itemId}", ['app_version' => 'v2']);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Timeout: ' . $e->getMessage()];
        }

        if ($response->failed()) {
            return ['success' => false, 'error' => "HTTP {$response->status()}"];
        }

        $data = $response->json();
        $promotions = [];

        foreach ($data as $promo) {
            $netProceeds = $this->extrairNetProceedsAmount($promo['net_proceeds'] ?? null);

            $promotions[] = [
                'id' => $promo['promotion_id'] ?? $promo['id'] ?? '',
                'name' => $promo['name'] ?? $promo['promotion_name'] ?? 'Sem nome',
                'type' => $promo['type'] ?? $promo['promotion_type'] ?? '',
                'status' => $promo['status'] ?? '',
                'price' => $promo['price'] ?? null,
                'original_price' => $promo['original_price'] ?? null,
                'meli_percentage' => (float) ($promo['meli_percentage'] ?? 0),
                'seller_percentage' => (float) ($promo['seller_percentage'] ?? 0),
                'net_proceeds' => $promo['net_proceeds'] ?? null,
                'net_proceeds_amount' => $netProceeds,
                'start_date' => $promo['start_date'] ?? null,
                'finish_date' => $promo['finish_date'] ?? null,
                'offer_id' => $promo['offer_id'] ?? $promo['ref_id'] ?? $promo['deal_id'] ?? $promo['candidate_id'] ?? null,
                'raw_keys' => array_keys($promo),
            ];
        }

        return ['success' => true, 'promotions' => $promotions];
    }

    public function buscarTitulosEmBatch(array $itemIds): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token || empty($itemIds)) return [];

        $titulos = [];
        $chunks = array_chunk($itemIds, 20);

        foreach ($chunks as $chunk) {
            try {
                $ids = implode(',', $chunk);
                $response = Http::withToken($token)
                    ->withOptions(['verify' => false])
                    ->timeout(15)
                    ->get("{$this->apiBase}/items", ['ids' => $ids]);

                if ($response->successful()) {
                    foreach ($response->json() as $result) {
                        if (($result['code'] ?? 0) == 200 && !empty($result['body']['title'])) {
                            $titulos[$result['body']['id']] = $result['body']['title'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Continuar sem títulos
            }
        }

        return $titulos;
    }
}
