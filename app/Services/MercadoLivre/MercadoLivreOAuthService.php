<?php

namespace App\Services\MercadoLivre;

use App\Models\MercadoLivreToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoLivreOAuthService
{
    private string $accountKey;
    private array $accountConfig;

    public function __construct(string $accountKey)
    {
        $this->accountKey = $accountKey;
        $this->accountConfig = config("mercadolivre.accounts.{$accountKey}");

        if (!$this->accountConfig) {
            throw new \InvalidArgumentException("Conta ML '{$accountKey}' não configurada.");
        }
    }

    public function getAuthorizationUrl(): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->accountConfig['client_id'],
            'redirect_uri' => $this->accountConfig['redirect_uri'],
            'state' => $this->accountKey,
        ]);

        return config('mercadolivre.oauth_authorize') . '?' . $params;
    }

    public function exchangeCodeForToken(string $code): ?MercadoLivreToken
    {
        $response = Http::withOptions(['verify' => false])
            ->post(config('mercadolivre.oauth_token'), [
                'grant_type' => 'authorization_code',
                'client_id' => $this->accountConfig['client_id'],
                'client_secret' => $this->accountConfig['client_secret'],
                'code' => $code,
                'redirect_uri' => $this->accountConfig['redirect_uri'],
            ]);

        if ($response->failed()) {
            Log::error("ML OAuth [{$this->accountKey}]: Erro ao trocar code por token", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();

        return MercadoLivreToken::updateOrCreate(
            ['account_key' => $this->accountKey],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'user_id' => (string) ($data['user_id'] ?? $this->accountConfig['user_id'] ?? ''),
                'expires_at' => now()->addSeconds($data['expires_in'] ?? 21600),
            ]
        );
    }

    public function getAccessToken(): ?string
    {
        $token = MercadoLivreToken::where('account_key', $this->accountKey)->first();

        if (!$token) {
            return null;
        }

        if (!$token->isExpired()) {
            return $token->access_token;
        }

        if ($token->refresh_token) {
            return $this->refreshAccessToken($token);
        }

        return null;
    }

    private function refreshAccessToken(MercadoLivreToken $token): ?string
    {
        $response = Http::withOptions(['verify' => false])
            ->post(config('mercadolivre.oauth_token'), [
                'grant_type' => 'refresh_token',
                'client_id' => $this->accountConfig['client_id'],
                'client_secret' => $this->accountConfig['client_secret'],
                'refresh_token' => $token->refresh_token,
            ]);

        if ($response->failed()) {
            Log::error("ML OAuth [{$this->accountKey}]: Erro ao renovar token", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 21600),
        ]);

        Log::info("ML OAuth [{$this->accountKey}]: Token renovado com sucesso");

        return $data['access_token'];
    }

    public function isAuthorized(): bool
    {
        return MercadoLivreToken::where('account_key', $this->accountKey)->exists();
    }

    public function getAccountName(): string
    {
        return $this->accountConfig['name'] ?? $this->accountKey;
    }
}
