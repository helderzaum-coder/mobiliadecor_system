<?php

namespace App\Services\Bling;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlingClient
{
    private string $apiBase;
    private string $accountKey;
    private BlingOAuthService $oauth;

    public function __construct(string $accountKey)
    {
        $this->accountKey = $accountKey;
        $this->apiBase = rtrim(config('bling.api_base'), '/');
        $this->oauth = new BlingOAuthService($accountKey);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $query = [], array $body = []): array
    {
        return $this->request('POST', $path, $query, $body);
    }

    public function put(string $path, array $query = [], array $body = []): array
    {
        return $this->request('PUT', $path, $query, $body);
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null, bool $isRetry = false): array
    {
        $token = $this->oauth->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'http_code' => 401,
                'body' => ['error' => "Conta '{$this->accountKey}' não autorizada. Execute a autorização OAuth primeiro."],
            ];
        }

        $url = $this->apiBase . $path;

        $request = Http::withToken($token)
            ->withOptions(['verify' => false])
            ->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $query),
            'POST' => $request->post($url, $body),
            'PUT' => $request->put($url, $body),
            default => $request->get($url, $query),
        };

        // Se 401 e não é retry, tenta renovar token
        if ($response->status() === 401 && !$isRetry) {
            Log::warning("Bling [{$this->accountKey}]: Token expirado (401), renovando...");
            $newToken = $this->oauth->getAccessToken();

            if ($newToken && $newToken !== $token) {
                return $this->request($method, $path, $query, $body, true);
            }
        }

        return [
            'success' => $response->successful(),
            'http_code' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    /**
     * Busca pedidos de venda com paginação
     */
    public function getPedidos(array $params = []): array
    {
        return $this->get('/pedidos/vendas', $params);
    }

    /**
     * Busca um pedido específico
     */
    public function getPedido(int $id): array
    {
        return $this->get("/pedidos/vendas/{$id}");
    }

    /**
     * Busca produto pelo SKU
     */
    public function getProductBySku(string $sku): ?array
    {
        $res = $this->get('/produtos', ['codigo' => $sku, 'limite' => 100]);

        if ($res['success'] && !empty($res['body']['data'])) {
            foreach ($res['body']['data'] as $produto) {
                if (($produto['codigo'] ?? '') === $sku) {
                    return $produto;
                }
            }
        }

        return null;
    }

    public function isAuthorized(): bool
    {
        return $this->oauth->isAuthorized();
    }
}
