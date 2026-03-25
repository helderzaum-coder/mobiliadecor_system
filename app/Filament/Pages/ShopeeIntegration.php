<?php

namespace App\Filament\Pages;

use App\Services\Shopee\ShopeeClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ShopeeIntegration extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Shopee API';
    protected static ?string $title           = 'Integração Shopee';
    protected static string  $view            = 'filament.pages.shopee-integration';

    public bool $authorized = false;
    public ?int $shopId     = null;
    public bool $sandbox    = true;

    public function mount(): void
    {
        $client = new ShopeeClient();
        $this->authorized = $client->isAuthorized();
        $this->shopId     = $client->getShopId();
        $this->sandbox    = config('shopee.sandbox', true);
    }

    public function conectar(): void
    {
        $client = new ShopeeClient();
        $url = $client->getAuthUrl();
        $this->redirect($url);
    }

    public function testConnection(): void
    {
        $client = new ShopeeClient();

        if (!$client->isAuthorized()) {
            Notification::make()->title('Não autorizado')->danger()->send();
            return;
        }

        $shopId = $client->getShopId();
        $res = $client->get('/api/v2/shop/get_shop_info', [], $shopId);

        if ($res['success']) {
            $nome = $res['body']['shop_name'] ?? 'N/A';
            Notification::make()
                ->title("Conexão OK — Loja: {$nome}")
                ->success()
                ->send();
        } else {
            $erro = $res['body']['message'] ?? $res['body']['error'] ?? 'Erro desconhecido';
            Notification::make()
                ->title("Erro: {$erro}")
                ->danger()
                ->send();
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
