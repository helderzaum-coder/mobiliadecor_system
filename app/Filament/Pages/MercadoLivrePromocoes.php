<?php

namespace App\Filament\Pages;

use App\Services\MercadoLivre\MercadoLivrePromotionService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class MercadoLivrePromocoes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'ML Promoções';
    protected static ?string $title = 'Promoções Mercado Livre';
    protected static string $view = 'filament.pages.mercado-livre-promocoes';
    protected static ?int $navigationSort = 3;

    public string $accountKey = 'primary';
    public array $promotions = [];
    public ?array $selectedPromotion = null;
    public array $items = [];
    public int $totalItems = 0;
    public ?string $searchAfter = null;
    public bool $loading = false;
    public bool $needsLoadItems = false;
    public ?string $editingItemId = null;
    public ?float $editingDealPrice = null;

    public function mount(): void
    {
        $this->loadPromotions();
    }

    public function loadPromotions(): void
    {
        $service = new MercadoLivrePromotionService($this->accountKey);
        $result = $service->listarPromocoes();

        if ($result['success']) {
            $this->promotions = $result['promotions'];
        } else {
            Notification::make()->title('Erro ao carregar promoções')->body($result['error'] ?? '')->danger()->send();
        }
    }

    public function switchAccount(string $key): void
    {
        $this->accountKey = $key;
        $this->promotions = [];
        $this->selectedPromotion = null;
        $this->items = [];
        $this->searchAfter = null;
        $this->loadPromotions();
    }

    public function selectPromotion(int $index): void
    {
        $this->selectedPromotion = $this->promotions[$index] ?? null;
        $this->items = [];
        $this->searchAfter = null;
        $this->totalItems = 0;
        $this->loading = true;
        $this->needsLoadItems = true;
    }

    public function doLoadItems(): void
    {
        if (!$this->needsLoadItems || !$this->selectedPromotion) return;
        $this->needsLoadItems = false;

        try {
            $this->loadItems();
        } catch (\Throwable $e) {
            Notification::make()->title('Erro ao carregar itens')->body($e->getMessage())->danger()->send();
        }
        $this->loading = false;
    }

    public function loadItems(): void
    {
        if (!$this->selectedPromotion) return;

        $service = new MercadoLivrePromotionService($this->accountKey);
        $result = $service->listarItensDaPromocao(
            $this->selectedPromotion['id'],
            $this->selectedPromotion['type'],
            $this->searchAfter
        );

        if ($result['success']) {
            $this->items = array_merge($this->items, $result['items']);
            $this->totalItems = $result['total'];
            $this->searchAfter = $result['search_after'];
        } else {
            Notification::make()->title('Erro ao carregar itens')->body($result['error'] ?? '')->danger()->send();
        }
    }

    public function loadAllItems(): void
    {
        if (!$this->selectedPromotion) return;

        $service = new MercadoLivrePromotionService($this->accountKey);
        $result = $service->listarTodosItensDaPromocao(
            $this->selectedPromotion['id'],
            $this->selectedPromotion['type']
        );

        if ($result['success']) {
            $this->items = $result['items'];
            $this->totalItems = $result['total'];
            $this->searchAfter = null;
        }
    }

    public function aderirItem(string $itemId): void
    {
        if (!$this->selectedPromotion) return;

        // Encontrar o item na lista para pegar o preço
        $targetItem = null;
        foreach ($this->items as $item) {
            if ($item['id'] === $itemId) {
                $targetItem = $item;
                break;
            }
        }

        if (!$targetItem) {
            Notification::make()->title('Item não encontrado')->danger()->send();
            return;
        }

        // Usar deal_price se existir, senão price
        $dealPrice = $targetItem['deal_price'] ?? $targetItem['price'] ?? null;
        if (!$dealPrice) {
            Notification::make()->title('Preço não disponível para adesão')->danger()->send();
            return;
        }

        $service = new MercadoLivrePromotionService($this->accountKey);
        $result = $service->aderirPromocao(
            $itemId,
            $this->selectedPromotion['id'],
            $this->selectedPromotion['type'],
            (float) $dealPrice
        );

        if ($result['success']) {
            // Atualizar status local
            foreach ($this->items as &$item) {
                if ($item['id'] === $itemId) {
                    $item['status'] = 'active';
                    break;
                }
            }
            Notification::make()->title('Item adicionado à promoção!')->success()->send();
        } else {
            Notification::make()->title('Erro ao aderir')->body($result['error'] ?? '')->danger()->send();
        }
    }

    public function removeItem(string $itemId): void
    {
        if (!$this->selectedPromotion) return;

        $service = new MercadoLivrePromotionService($this->accountKey);
        $result = $service->removerItemDaPromocao(
            $itemId,
            $this->selectedPromotion['id'],
            $this->selectedPromotion['type']
        );

        if ($result['success']) {
            $this->items = array_filter($this->items, fn($i) => $i['id'] !== $itemId);
            $this->items = array_values($this->items);
            Notification::make()->title('Item removido da promoção')->success()->send();
        } else {
            Notification::make()->title('Erro ao remover')->body($result['error'] ?? '')->danger()->send();
        }
    }

    public function startEditPrice(string $itemId, ?float $currentPrice): void
    {
        $this->editingItemId = $itemId;
        $this->editingDealPrice = $currentPrice;
    }

    public function cancelEdit(): void
    {
        $this->editingItemId = null;
        $this->editingDealPrice = null;
    }

    public function savePrice(): void
    {
        if (!$this->selectedPromotion || !$this->editingItemId || !$this->editingDealPrice) return;

        $service = new MercadoLivrePromotionService($this->accountKey);
        $result = $service->editarPrecoPromocional(
            $this->editingItemId,
            $this->selectedPromotion['id'],
            $this->selectedPromotion['type'],
            $this->editingDealPrice
        );

        if ($result['success']) {
            // Atualizar na lista local
            foreach ($this->items as &$item) {
                if ($item['id'] === $this->editingItemId) {
                    $item['deal_price'] = $this->editingDealPrice;
                    break;
                }
            }
            $this->cancelEdit();
            Notification::make()->title('Preço promocional atualizado')->success()->send();
        } else {
            Notification::make()->title('Erro ao atualizar preço')->body($result['error'] ?? '')->danger()->send();
        }
    }

    public function getAccounts(): array
    {
        $accounts = [];
        foreach (config('mercadolivre.accounts') as $key => $account) {
            $accounts[$key] = $account['name'];
        }
        return $accounts;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
