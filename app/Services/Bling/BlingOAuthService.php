<?php

namespace App\Services\Bling;

use App\Models\BlingToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlingOAuthService
{
    private string $accountKey;
    private array $accountConfig;

    public function __construct(string $accountKey)
    {
        $this->accountKey = $accountKey;
        $this->accountConfig = config("bling.accounts.{$accountKey}");

        if (!$this->accountConfig) {
            throw new \InvalidArgumentException("Conta Bling '{$accountKey}' não configurada.");
        }
    }

    public function getAuthorizationUrl(): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->accountConfig['client_id'],
            'redirect_uri' => route('bling.callback'),
            'scope' => config('bling.scopes'),
            'state' => $this->accountKey,
        ]);

        return config('bling.oauth_authorize') . '?' . $params;
    }

    public function exchangeCodeForToken(string $code): ?BlingToken
    {
        $response = Http::withBasicAuth(
            $this->accountConfig['client_id'],
            $this->accountConfig['client_secret']
        )->withOptions(['verify' => false])->asForm()->post(config('bling.oauth_token'), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => route('bling.callback'),
        ]);

        if ($response->failed()) {
            Log::error("Bling OAuth [{$this->accountKey}]: Erro ao trocar code por token", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();

        return BlingToken::updateOrCreate(
            ['account_key' => $this->accountKey],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]
        );
    }

    public function getAccessToken(): ?string
    {
        $token = BlingToken::where('account_key', $this->accountKey)->first();

        if (!$token) {
            return null;
        }

        if (!$token->isExpired()) {
            return $token->access_token;
        }

        // Token expirado, renovar com refresh_token
        if ($token->refresh_token) {
            return $this->refreshAccessToken($token);
        }

        return null;
    }

    private function refreshAccessToken(BlingToken $token): ?string
    {
        $response = Http::withBasicAuth(
            $this->accountConfig['client_id'],
            $this->accountConfig['client_secret']
        )->withOptions(['verify' => false])->asForm()->post(config('bling.oauth_token'), [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error("Bling OAuth [{$this->accountKey}]: Erro ao renovar token", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
        ]);

        Log::info("Bling OAuth [{$this->accountKey}]: Token renovado com sucesso");

        return $data['access_token'];
    }

    public function isAuthorized(): bool
    {
        return BlingToken::where('account_key', $this->accountKey)->exists();
    }

    public function getAccountName(): string
    {
        return $this->accountConfig['name'] ?? $this->accountKey;
    }
}
