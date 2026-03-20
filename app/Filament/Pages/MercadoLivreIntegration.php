<?php

namespace App\Filament\Pages;

use App\Services\MercadoLivre\MercadoLivreOAuthService;
use Filament\Pages\Page;

class MercadoLivreIntegration extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Mercado Livre';
    protected static ?string $title = 'Integração Mercado Livre';
    protected static string $view = 'filament.pages.mercado-livre-integration';
    protected static ?int $navigationSort = 2;

    public function getAccounts(): array
    {
        $accounts = [];

        foreach (config('mercadolivre.accounts') as $key => $account) {
            $oauth = new MercadoLivreOAuthService($key);
            $token = \App\Models\MercadoLivreToken::where('account_key', $key)->first();

            $accounts[$key] = [
                'name' => $account['name'],
                'authorized' => $oauth->isAuthorized(),
                'key' => $key,
                'expires_at' => $token?->expires_at?->format('d/m/Y H:i') ?? null,
                'user_id' => $token?->user_id ?? ($account['user_id'] ?? null),
            ];
        }

        return $accounts;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
