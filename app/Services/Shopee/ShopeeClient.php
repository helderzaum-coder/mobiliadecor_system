<?php

namespace App\Services\Shopee;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeClient
{
    private int $partnerId;
    private string $partnerKey;
    private string $host;

    public function __construct()
    {
        $this->partnerId  = config('shopee.partner_id');
        $this->partnerKey = config('shopee.partner_key');
        $this->host       = config('shopee.sandbox')
            ? config('shopee.host_sandbox')
            : config('shopee.host_live');
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPartnerId(): int
    {
        return $this->partnerId;
    }

    /**
     * Gera assinatura HMAC-SHA256 para a API Shopee v2.
     */
    public function sign(string $path, int $timestamp, ?string $accessToken = null, ?int $shopId = null): string
    {
        $baseString = (string)$this->partnerId . $path . $timestamp;
        if ($accessToken) {
            $baseString .= $accessToken;
        }
        if ($shopId) {
            $baseString .= $shopId;
        }
        return hash_hmac('sha256', $baseString, $this->partnerKey);
    }

    /**
     * Gera URL de autorização da loja.
     */
    public function getAuthUrl(): string
    {
        $path = '/api/v2/shop/auth_partner';
        $timestamp = time();
        $sign = $this->sign($path, $timestamp);
        
        $redirectUri = config('shopee.redirect_uri');

        // O http_build_query já faz o urlencode necessário. 
        // Se fizermos manualmente antes, a URL fica "double encoded" e a Shopee rejeita.
        $params = [
            'partner_id' => (int) $this->partnerId,
            'timestamp'  => $timestamp,
            'sign'       => $sign,
            'redirect'   => $redirectUri,
        ];

        $url = "{$this->host}{$path}?" . http_build_query($params);
        
        Log::info('Shopee Auth URL', [
            'url' => $url,
        ]);

        return $url;
    }

    /**
     * Troca o code por access_token.
     */
    public function getAccessToken(string $code, int $shopId): ?array
    {
        $path = '/api/v2/auth/token/get';
        $timestamp = time();
        $sign = $this->sign($path, $timestamp);

        $url = "{$this->host}{$path}?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}";

        $response = Http::withOptions(['verify' => false])
            ->post($url, [
                'code'       => $code,
                'shop_id'    => $shopId,
                'partner_id' => $this->partnerId,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['access_token'])) {
                $this->storeTokens($shopId, $data);
                return $data;
            }
            Log::warning('Shopee: token response sem access_token', $data);
        } else {
            Log::error('Shopee: erro ao obter token', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
        }

        return null;
    }

    /**
     * Renova o access_token usando refresh_token.
     */
    public function refreshToken(int $shopId): ?array
    {
        $refreshToken = Cache::get("shopee_refresh_token_{$shopId}");
        if (!$refreshToken) return null;

        $path = '/api/v2/auth/access_token/get';
        $timestamp = time();
        $sign = $this->sign($path, $timestamp);

        $url = "{$this->host}{$path}?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}";

        $response = Http::withOptions(['verify' => false])
            ->post($url, [
                'refresh_token' => $refreshToken,
                'shop_id'       => $shopId,
                'partner_id'    => $this->partnerId,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['access_token'])) {
                $this->storeTokens($shopId, $data);
                return $data;
            }
        }

        Log::error('Shopee: erro ao renovar token', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        return null;
    }

    /**
     * Faz requisição autenticada à API Shopee.
     */
    public function get(string $path, array $query = [], ?int $shopId = null): array
    {
        return $this->request('GET', $path, $query, null, $shopId);
    }

    public function post(string $path, array $body = [], ?int $shopId = null): array
    {
        return $this->request('POST', $path, [], $body, $shopId);
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null, ?int $shopId = null): array
    {
        $shopId = $shopId ?: (int) Cache::get('shopee_shop_id');
        $accessToken = Cache::get("shopee_access_token_{$shopId}");

        if (!$accessToken) {
            // Tentar renovar
            $refreshed = $this->refreshToken($shopId);
            if (!$refreshed) {
                return ['success' => false, 'error' => 'Não autorizado. Faça login na Shopee.'];
            }
            $accessToken = $refreshed['access_token'];
        }

        $timestamp = time();
        $sign = $this->sign($path, $timestamp, $accessToken, $shopId);

        $commonParams = [
            'partner_id'   => $this->partnerId,
            'timestamp'    => $timestamp,
            'sign'         => $sign,
            'access_token' => $accessToken,
            'shop_id'      => $shopId,
        ];

        $url = $this->host . $path;

        $http = Http::withOptions(['verify' => false])->timeout(30);

        if ($method === 'GET') {
            $response = $http->get($url, array_merge($commonParams, $query));
        } else {
            $url .= '?' . http_build_query($commonParams);
            $response = $http->post($url, $body);
        }

        $json = $response->json() ?? [];

        // Se token expirou, renovar e tentar de novo
        if (($json['error'] ?? '') === 'error_auth') {
            $refreshed = $this->refreshToken($shopId);
            if ($refreshed) {
                return $this->request($method, $path, $query, $body, $shopId);
            }
        }

        return [
            'success'   => $response->successful() && empty($json['error']),
            'http_code' => $response->status(),
            'body'      => $json,
        ];
    }

    private function storeTokens(int $shopId, array $data): void
    {
        $expireSeconds = $data['expire_in'] ?? 14400; // 4 horas padrão
        Cache::put("shopee_access_token_{$shopId}", $data['access_token'], now()->addSeconds($expireSeconds - 300));
        Cache::put("shopee_refresh_token_{$shopId}", $data['refresh_token'], now()->addDays(30));
        Cache::put('shopee_shop_id', $shopId, now()->addDays(30));

        Log::info("Shopee: tokens salvos para shop #{$shopId}", [
            'expire_in' => $expireSeconds,
        ]);
    }

    /**
     * Verifica se está autorizado.
     */
    public function isAuthorized(?int $shopId = null): bool
    {
        $shopId = $shopId ?: (int) Cache::get('shopee_shop_id');
        return $shopId > 0 && (
            Cache::has("shopee_access_token_{$shopId}") ||
            Cache::has("shopee_refresh_token_{$shopId}")
        );
    }

    public function getShopId(): ?int
    {
        return Cache::get('shopee_shop_id') ?: null;
    }
}
