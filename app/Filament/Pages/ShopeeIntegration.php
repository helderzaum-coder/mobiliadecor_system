<?php

namespace App\Filament\Pages;

use App\Services\Shopee\ShopeeService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Laraditz\Shopee\Facades\Shopee;

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
        $this->authorized = ShopeeService::isAuthorized();
        $this->shopId     = ShopeeService::getShopId();
        $this->sandbox    = config('shopee.sandbox.mode', true);
    }

    public function conectar(): void
    {
        $url = Shopee::shop()->generateAuthorizationURL();
        $this->redirect($url);
    }

    public function testConnection(): void
    {
        if (!ShopeeService::isAuthorized()) {
            Notification::make()->title('Não autorizado')->danger()->send();
            return;
        }

        $shopId = ShopeeService::getShopId();
        $res = Shopee::make(shop_id: $shopId)->shop()->getShopInfo();

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
