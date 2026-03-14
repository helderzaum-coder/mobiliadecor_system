<?php

namespace App\Filament\Pages;

use App\Services\Bling\BlingOAuthService;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class BlingIntegration extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Bling';
    protected static ?string $title = 'Integração Bling';
    protected static string $view = 'filament.pages.bling-integration';

    public function getAccounts(): array
    {
        $accounts = [];

        foreach (config('bling.accounts') as $key => $account) {
            $oauth = new BlingOAuthService($key);
            $accounts[$key] = [
                'name' => $account['name'],
                'authorized' => $oauth->isAuthorized(),
                'key' => $key,
            ];
        }

        return $accounts;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
