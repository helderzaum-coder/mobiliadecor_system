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
    public string $searchItem = '';

    public function searchItems(): void
    {
        // Se ainda há itens não carregados, carregar todos para buscar
        if ($this->searchItem && $this->searchAfter) {
            $this->loadAllItems();
        }
    }

    public function getFilteredItems(): array
    {
        if (!$this->searchItem) return $this->items;

        $term = strtolower(trim($this->searchItem));
        return array_filter($this->items, function ($item) use ($term) {
            // Buscar no ID (com ou sem prefixo MLB)
            $id = strtolower($item['id'] ?? '');
            $idSemPrefixo = preg_replace('/^mlb/', '', $id);
            if (str_contains($id, $term) || str_contains($idSemPrefixo, $term)) return true;
            // Buscar no título
            if (str_contains(strtolower($item['title'] ?? ''), $term)) return true;
            return false;
        });
    }
    public ?string $aderindoItemId = null;
    public ?float $aderindoPreco = null;
    public ?array $aderindoInfo = null; // dados enriquecidos para simulação
    public string $buscarItemId = '';
    public array $promocoesDoItem = [];
    public string $abaAtiva = 'promocoes'; // 'promocoes' ou 'buscar_item'
    public float $impostoPercent = 17.8;

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
            $newItems = $result['items'];

            // Buscar títulos em batch para itens sem título
            $semTitulo = array_filter($newItems, fn($i) => empty($i['title']) || $i['title'] === $i['id']);
            if (!empty($semTitulo)) {
                $ids = array_column($semTitulo, 'id');
                $titulos = $service->buscarTitulosEmBatch($ids);
                foreach ($newItems as &$item) {
                    if (isset($titulos[$item['id']])) {
                        $item['title'] = $titulos[$item['id']];
                    }
                }
            }

            $this->items = array_merge($this->items, $newItems);
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

    public function iniciarAdesao(string $itemId): void
    {
        $this->aderindoItemId = $itemId;
        $this->aderindoInfo = null;

        $targetItem = null;
        foreach ($this->items as $item) {
            if ($item['id'] === $itemId) {
                $targetItem = $item;
                break;
            }
        }

        $this->aderindoPreco = $targetItem['deal_price'] ?? $targetItem['price'] ?? null;

        $service = new MercadoLivrePromotionService($this->accountKey);
        $info = $service->buscarInfoParaAdesao($itemId);

        $promoType = $this->selectedPromotion['type'] ?? '';
        $meliPercentage = (float) ($targetItem['meli_percentage'] ?? 0);
        $sellerPercentage = (float) ($targetItem['seller_percentage'] ?? 0);
        $temSubsidio = $meliPercentage > 0;

        $this->aderindoInfo = [
            'title'              => $targetItem['title'] ?? $itemId,
            'original_price'     => $info['base_price'] ?? $targetItem['price'] ?? 0,
            'frete'              => $info['frete'] ?? 0,
            'comissao_percent'   => $info['comissao_percent'] ?? 11.5,
            'listing_type'       => $info['listing_type'] ?? '',
            'imposto_percent'    => $this->impostoPercent,
            'custo_produto'      => $info['custo_produto'] ?? 0,
            'sku'                => $info['sku'] ?? null,
            'tem_subsidio'       => $temSubsidio,
            'meli_percentage'    => $meliPercentage,
            'seller_percentage'  => $sellerPercentage,
            'promo_type'         => $promoType,
        ];
    }

    public function cancelarAdesao(): void
    {
        $this->aderindoItemId = null;
        $this->aderindoPreco = null;
    }

    public function confirmarAdesao(): void
    {
        if (!$this->selectedPromotion || !$this->aderindoItemId || !$this->aderindoPreco) {
            Notification::make()->title('Preencha o preço para aderir')->warning()->send();
            return;
        }

        $service = new MercadoLivrePromotionService($this->accountKey);
        $result = $service->aderirPromocao(
            $this->aderindoItemId,
            $this->selectedPromotion['id'],
            $this->selectedPromotion['type'],
            (float) $this->aderindoPreco
        );

        if ($result['success']) {
            foreach ($this->items as &$item) {
                if ($item['id'] === $this->aderindoItemId) {
                    $item['status'] = 'active';
                    $item['deal_price'] = $this->aderindoPreco;
                    break;
                }
            }
            Notification::make()->title('Adesão realizada!')->body("R$ " . number_format($this->aderindoPreco, 2, ',', '.'))->success()->send();
            $this->cancelarAdesao();
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

    public function buscarPromocoesDoItem(): void
    {
        if (!$this->buscarItemId) {
            Notification::make()->title('Digite um MLB ID')->warning()->send();
            return;
        }

        $itemId = trim($this->buscarItemId);
        // Adicionar prefixo MLB se não tiver
        if (!str_starts_with(strtoupper($itemId), 'MLB')) {
            $itemId = 'MLB' . $itemId;
        }

        $service = new MercadoLivrePromotionService($this->accountKey);
        $result = $service->buscarPromocoesParaItem($itemId);

        if ($result['success']) {
            $this->promocoesDoItem = $result['promotions'];
            if (empty($this->promocoesDoItem)) {
                Notification::make()->title('Nenhuma promoção encontrada para ' . $itemId)->warning()->send();
            }
        } else {
            Notification::make()->title('Erro ao buscar promoções')->body($result['error'] ?? '')->danger()->send();
            $this->promocoesDoItem = [];
        }
    }

    public function iniciarAdesaoDoItem(int $promoIndex): void
    {
        $promo = $this->promocoesDoItem[$promoIndex] ?? null;
        if (!$promo) return;

        $itemId = trim($this->buscarItemId);
        if (!str_starts_with(strtoupper($itemId), 'MLB')) {
            $itemId = 'MLB' . $itemId;
        }

        $this->aderindoItemId = $itemId;
        $this->aderindoInfo = null;
        $this->aderindoPreco = $promo['price'] ?? null;

        $this->selectedPromotion = [
            'id' => $promo['id'],
            'type' => $promo['type'],
            'name' => $promo['name'],
        ];

        $service = new MercadoLivrePromotionService($this->accountKey);
        $info = $service->buscarInfoParaAdesao($itemId);

        $meliPercentage = (float) ($promo['meli_percentage'] ?? 0);
        $sellerPercentage = (float) ($promo['seller_percentage'] ?? 0);

        $this->aderindoInfo = [
            'title'              => $info['title'] ?? $itemId,
            'original_price'     => $info['base_price'] ?? $promo['original_price'] ?? 0,
            'frete'              => $info['frete'] ?? 0,
            'comissao_percent'   => $info['comissao_percent'] ?? 11.5,
            'listing_type'       => $info['listing_type'] ?? '',
            'imposto_percent'    => $this->impostoPercent,
            'custo_produto'      => $info['custo_produto'] ?? 0,
            'sku'                => $info['sku'] ?? null,
            'tem_subsidio'       => $meliPercentage > 0,
            'meli_percentage'    => $meliPercentage,
            'seller_percentage'  => $sellerPercentage,
            'promo_type'         => $promo['type'],
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
