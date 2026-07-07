<?php

namespace App\Services\MercadoLivre;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoLivreClient
{
    private string $apiBase; 
    private string $accountKey;
    private MercadoLivreOAuthService $oauth;

    public function __construct(string $accountKey)
    {
        $this->accountKey = $accountKey;
        $this->apiBase = rtrim(config('mercadolivre.api_base'), '/');
        $this->oauth = new MercadoLivreOAuthService($accountKey);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, [], $body);
    }

    private function request(string $method, string $path, array $query = [], array|bool $bodyOrRetry = false, bool $isRetry = false): array
    {
        // Compatibilidade: se bodyOrRetry é bool, é o antigo parâmetro isRetry
        $body = [];
        if (is_array($bodyOrRetry)) {
            $body = $bodyOrRetry;
        } elseif (is_bool($bodyOrRetry)) {
            $isRetry = $bodyOrRetry;
        }

        $token = $this->oauth->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'http_code' => 401,
                'body' => ['error' => "Conta ML '{$this->accountKey}' não autorizada."],
            ];
        }

        $url = $this->apiBase . $path;

        $http = Http::withToken($token)->withOptions(['verify' => false])->timeout(30);

        $response = match ($method) {
            'PUT' => $http->put($url, $body),
            default => $http->get($url, $query),
        };

        if ($response->status() === 401 && !$isRetry) {
            Log::warning("ML [{$this->accountKey}]: Token expirado (401), renovando...");
            return $this->request($method, $path, $query, $body, true);
        }

        return [
            'success' => $response->successful(),
            'http_code' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    public function isAuthorized(): bool
    {
        return $this->oauth->isAuthorized();
    }
}
