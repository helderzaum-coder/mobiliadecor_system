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
            $dealPrice = $item['deal_price'] ?? $item['proposed_deal_price'] ?? null;

            $items[] = [
                'id' => $item['id'] ?? '',
                'title' => $titulo,
                'status' => $item['status'] ?? '',
                'price' => $preco,
                'deal_price' => $dealPrice,
                'original_price' => $item['original_price'] ?? $preco,
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
        // 4. Não fazer fallback HTTP individual para evitar timeout
        return $item['id'] ?? 'Sem título';
    }
}
