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

            $items[] = [
                'id' => $item['id'] ?? '',
                'title' => $titulo,
                'status' => $item['status'] ?? '',
                'price' => $preco,
                'original_price' => $originalPrice,
                'deal_price' => $dealPrice ?? $proposedDealPrice,
                'proposed_deal_price' => $proposedDealPrice,
                'meli_percentage' => (float) ($item['meli_percentage'] ?? 0),
                'seller_percentage' => (float) ($item['seller_percentage'] ?? 0),
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

    public function aderirPromocao(string $itemId, string $promotionId, string $promotionType, float $dealPrice): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Token não disponível'];

        try {
            $response = Http::withToken($token)
                ->withOptions(['verify' => false])
                ->timeout(15)
                ->post("{$this->apiBase}/seller-promotions/items/{$itemId}?app_version=v2", [
                    'promotion_type' => $promotionType,
                    'promotion_id' => $promotionId,
                    'deal_price' => $dealPrice,
                ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Timeout: ' . $e->getMessage()];
        }

        if ($response->successful()) {
            return ['success' => true];
        }

        $body = $response->json();
        return ['success' => false, 'error' => "HTTP {$response->status()}: " . ($body['message'] ?? $response->body())];
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

    /**
     * Busca títulos em batch via multiget (até 20 por chamada)
     */
    public function buscarInfoParaAdesao(string $itemId): array
    {
        $token = $this->oauth->getAccessToken();
        if (!$token) return [];

        $info = ['base_price' => 0, 'frete' => 0, 'comissao_percent' => 0, 'listing_type' => '', 'sku' => null, 'custo_produto' => 0, 'title' => null];

        try {
            $resp = Http::withToken($token)->withOptions(['verify' => false])->timeout(10)
                ->get("{$this->apiBase}/items/{$itemId}");
            if ($resp->successful()) {
                $item = $resp->json();
                $info['base_price'] = $item['base_price'] ?? $item['price'] ?? 0;
                $info['listing_type'] = $item['listing_type_id'] ?? '';
                $info['comissao_percent'] = $this->percentualComissao($item['listing_type_id'] ?? null);
                $info['title'] = $item['title'] ?? null;

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

                // Frete grátis - buscar custo via shipping_options
                $freeShipping = $item['shipping']['free_shipping'] ?? false;
                if ($freeShipping) {
                    $tokenModel = \App\Models\MercadoLivreToken::where('account_key', $this->accountKey)->first();
                    $userId = $tokenModel?->user_id ?? config("mercadolivre.accounts.{$this->accountKey}.user_id");

                    $freteResp = Http::withToken($token)->withOptions(['verify' => false])->timeout(10)
                        ->get("{$this->apiBase}/users/{$userId}/shipping_options/free", ['item_id' => $itemId]);

                    if ($freteResp->successful()) {
                        $coverage = $freteResp->json()['coverage'] ?? [];
                        $info['frete'] = $coverage['all_country']['list_cost']
                            ?? collect($coverage)->flatten(1)->pluck('list_cost')->filter()->first()
                            ?? 0;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("ML buscarInfoParaAdesao [{$itemId}]: " . $e->getMessage());
        }

        return $info;
    }

    private function percentualComissao(?string $listingTypeId): float
    {
        return match ($listingTypeId) {
            'gold_special' => 11.5,
            'gold_pro' => 16.5,
            default => 11.5,
        };
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
            $promotions[] = [
                'id' => $promo['promotion_id'] ?? $promo['id'] ?? '',
                'name' => $promo['name'] ?? $promo['promotion_name'] ?? 'Sem nome',
                'type' => $promo['type'] ?? $promo['promotion_type'] ?? '',
                'status' => $promo['status'] ?? '',
                'price' => $promo['price'] ?? null,
                'original_price' => $promo['original_price'] ?? null,
                'meli_percentage' => (float) ($promo['meli_percentage'] ?? 0),
                'seller_percentage' => (float) ($promo['seller_percentage'] ?? 0),
                'start_date' => $promo['start_date'] ?? null,
                'finish_date' => $promo['finish_date'] ?? null,
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
