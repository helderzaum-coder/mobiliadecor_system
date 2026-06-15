<?php

namespace App\Filament\Pages;

use App\Models\MercadoLivreToken;
use App\Models\RelatorioMargemML;
use App\Services\Bling\BlingClient;
use App\Services\MercadoLivre\MercadoLivreClient;
use App\Services\MercadoLivre\MercadoLivrePromotionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ListagemAnunciosML extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Mercado Livre';
    protected static ?string $navigationLabel = 'Anúncios por Família';
    protected static ?string $title = 'Anúncios ML - Agrupados por Família';
    protected static string $view = 'filament.pages.listagem-anuncios-ml';
    protected static ?int $navigationSort = 25;

    public string $filtroAccount = '';
    public string $filtroStatus = '';
    public string $filtroCatalogo = '';
    public string $filtroMargem = '';
    public string $busca = '';
    public string $ordenar = 'family_name';

    // Busca em tempo real
    public string $buscaFamiliaRealtime = '';
    public ?array $resultadoRealtime = null;
    public bool $buscandoRealtime = false;

    public function getFamiliasProperty()
    {
        $query = RelatorioMargemML::query();

        if ($this->filtroAccount) {
            $query->where('account_key', $this->filtroAccount);
        }
        if ($this->filtroStatus === 'active') {
            $query->where('status_ml', 'active');
        } elseif ($this->filtroStatus === 'paused') {
            $query->where('status_ml', 'paused');
        } elseif ($this->filtroStatus === 'closed') {
            $query->where('status_ml', 'closed');
        }
        if ($this->filtroCatalogo === 'sim') {
            $query->where('is_catalog_listing', true);
        } elseif ($this->filtroCatalogo === 'nao') {
            $query->where('is_catalog_listing', false);
        }
        if ($this->filtroMargem === 'negativa') {
            $query->where('margem_pct', '<', 0);
        } elseif ($this->filtroMargem === 'baixa') {
            $query->whereBetween('margem_pct', [0, 15]);
        } elseif ($this->filtroMargem === 'media') {
            $query->whereBetween('margem_pct', [15, 30]);
        } elseif ($this->filtroMargem === 'boa') {
            $query->where('margem_pct', '>=', 30);
        }
        if ($this->busca) {
            $query->where(function ($q) {
                $q->where('titulo', 'like', "%{$this->busca}%")
                  ->orWhere('sku', 'like', "%{$this->busca}%")
                  ->orWhere('mlb_id', 'like', "%{$this->busca}%")
                  ->orWhere('family_name', 'like', "%{$this->busca}%")
                  ->orWhere('family_id', 'like', "%{$this->busca}%")
                  ->orWhere('user_product_id', 'like', "%{$this->busca}%");
            });
        }

        $items = $query->orderBy('family_name')->orderBy('user_product_id')->orderBy('listing_type')->get();

        // Agrupar por family_id (ou user_product_id se não tem family)
        $familias = [];
        foreach ($items as $item) {
            $key = $item->family_id ?: ($item->user_product_id ?: $item->mlb_id);
            if (!isset($familias[$key])) {
                $familias[$key] = [
                    'family_id' => $item->family_id,
                    'family_name' => $item->family_name ?: $item->titulo,
                    'ups' => [],
                ];
            }
            $upKey = $item->user_product_id ?: $item->mlb_id;
            if (!isset($familias[$key]['ups'][$upKey])) {
                $familias[$key]['ups'][$upKey] = [
                    'user_product_id' => $item->user_product_id,
                    'sku' => $item->sku,
                    'cor' => $item->cor,
                    'items' => [],
                ];
            }
            $familias[$key]['ups'][$upKey]['items'][] = $item;
        }

        // Ordenar famílias
        if ($this->ordenar === 'margem_asc') {
            uasort($familias, function ($a, $b) {
                $minA = collect($a['ups'])->flatMap(fn($up) => collect($up['items']))->min('margem_pct') ?? 999;
                $minB = collect($b['ups'])->flatMap(fn($up) => collect($up['items']))->min('margem_pct') ?? 999;
                return $minA <=> $minB;
            });
        } elseif ($this->ordenar === 'margem_desc') {
            uasort($familias, function ($a, $b) {
                $maxA = collect($a['ups'])->flatMap(fn($up) => collect($up['items']))->max('margem_pct') ?? 0;
                $maxB = collect($b['ups'])->flatMap(fn($up) => collect($up['items']))->max('margem_pct') ?? 0;
                return $maxB <=> $maxA;
            });
        }

        return $familias;
    }

    public function buscarFamiliaAgora(): void
    {
        $input = trim($this->buscaFamiliaRealtime);
        if (!$input) {
            Notification::make()->title('Informe um MLB ou MLBU.')->warning()->send();
            return;
        }

        $this->buscandoRealtime = true;
        $accountKey = $this->filtroAccount ?: 'primary';
        $client = new MercadoLivreClient($accountKey);

        if (!$client->isAuthorized()) {
            Notification::make()->title("Conta '{$accountKey}' não autorizada.")->danger()->send();
            $this->buscandoRealtime = false;
            return;
        }

        $tokenModel = MercadoLivreToken::where('account_key', $accountKey)->first();
        $userId = $tokenModel?->user_id ?? config("mercadolivre.accounts.{$accountKey}.user_id");

        // Determinar user_product_id
        if (str_starts_with(strtoupper($input), 'MLBU')) {
            $userProductId = strtoupper($input);
        } else {
            $itemResult = $client->get("/items/{$input}");
            if (!$itemResult['success']) {
                Notification::make()->title("Item não encontrado.")->danger()->send();
                $this->buscandoRealtime = false;
                return;
            }
            $userProductId = $itemResult['body']['user_product_id'] ?? null;
            if (!$userProductId) {
                Notification::make()->title("Item sem User Product (modelo antigo).")->warning()->send();
                $this->buscandoRealtime = false;
                return;
            }
        }

        // Buscar UP
        $upResult = $client->get("/user-products/{$userProductId}");
        if (!$upResult['success']) {
            Notification::make()->title("Erro ao buscar UP.")->danger()->send();
            $this->buscandoRealtime = false;
            return;
        }

        $up = $upResult['body'];
        $familyId = $up['family_id'] ?? null;
        $familyName = $up['family_name'] ?? $up['name'] ?? '—';

        // Buscar todos UPs da família
        $allUpIds = [$userProductId];
        if ($familyId) {
            sleep(1);
            $familyResult = $client->get("/sites/MLB/user-products-families/{$familyId}");
            if ($familyResult['success']) {
                $allUpIds = $familyResult['body']['user_products_ids'] ?? [$userProductId];
            }
        }

        // Montar resultado
        $resultado = [
            'family_id' => $familyId,
            'family_name' => $familyName,
            'ups' => [],
        ];

        foreach ($allUpIds as $upId) {
            sleep(1);
            $upData = ($upId === $userProductId) ? $up : null;
            if (!$upData) {
                $r = $client->get("/user-products/{$upId}");
                $upData = $r['success'] ? $r['body'] : null;
            }

            $sku = '—';
            $cor = '—';
            foreach ($upData['attributes'] ?? [] as $attr) {
                if ($attr['id'] === 'SELLER_SKU') $sku = $attr['values'][0]['name'] ?? '—';
                if ($attr['id'] === 'COLOR') $cor = $attr['values'][0]['name'] ?? '—';
            }

            // Buscar MLBs
            sleep(1);
            $searchResult = $client->get("/users/{$userId}/items/search", [
                'user_product_id' => $upId,
                'limit' => 50,
            ]);
            $mlbIds = $searchResult['success'] ? ($searchResult['body']['results'] ?? []) : [];

            $items = [];
            foreach ($mlbIds as $mlbId) {
                sleep(1);
                $mlbResult = $client->get("/items/{$mlbId}");
                if (!$mlbResult['success']) continue;

                $mlb = $mlbResult['body'];
                $mlbPrice = (float) ($mlb['price'] ?? 0);
                $mlbStatus = $mlb['status'] ?? '?';
                $mlbListingType = $mlb['listing_type_id'] ?? '?';
                $mlbCategoryId = $mlb['category_id'] ?? '';
                $mlbLogisticType = $mlb['shipping']['logistic_type'] ?? 'xd_drop_off';
                $mlbShippingMode = $mlb['shipping']['mode'] ?? 'me2';
                $mlbFreeShipping = $mlb['shipping']['free_shipping'] ?? false;

                $comissaoPct = 0;
                $comissaoValor = 0;
                $frete = 0;
                $custo = 0;
                $promocoes = [];

                if ($mlbStatus === 'active' && $mlbPrice > 0) {
                    // Comissão via API
                    sleep(1);
                    $promoService = new MercadoLivrePromotionService($accountKey);
                    $comData = $promoService->buscarComissaoParaPreco(
                        $mlbPrice, $mlbListingType, $mlbCategoryId, $mlbLogisticType, $mlbShippingMode
                    );
                    $comissaoPct = $comData['percent'] ?? 0;
                    $comissaoValor = $comData['valor'] ?? round($mlbPrice * $comissaoPct / 100, 2);
                    if ($comissaoValor <= 0) $comissaoValor = round($mlbPrice * $comissaoPct / 100, 2);

                    // Frete via shipping_options
                    if ($mlbFreeShipping) {
                        sleep(1);
                        $freteResult = $client->get("/items/{$mlbId}/shipping_options", ['zip_code' => '01310100']);
                        if ($freteResult['success'] && !empty($freteResult['body']['options'])) {
                            foreach ($freteResult['body']['options'] as $opt) {
                                $frete = max($frete, (float) ($opt['list_cost'] ?? 0));
                            }
                        }
                        $frete = round($frete, 2);
                    }

                    // Custo via Bling (SKU)
                    $mlbSku = $up['sku'] ?? null;
                    if ($mlbSku && $mlbSku !== '—') {
                        try {
                            $bling = new BlingClient($accountKey);
                            $produto = $bling->getProductBySku($mlbSku);
                            $custo = (float) ($produto['precoCusto'] ?? 0);
                            if ($custo <= 0 && !empty($produto['id'])) {
                                $detalhe = $bling->getProductById((int) $produto['id']);
                                $custo = (float) ($detalhe['precoCusto'] ?? 0);
                            }
                        } catch (\Throwable $e) {}
                    }

                    // Promoções
                    sleep(1);
                    $promoResult = $promoService->buscarPromocoesParaItem($mlbId);
                    if ($promoResult['success'] && !empty($promoResult['promotions'])) {
                        foreach ($promoResult['promotions'] as $promo) {
                            $pp = (float) ($promo['price'] ?? 0);
                            if ($pp <= 0) $pp = (float) ($promo['max_discounted_price'] ?? 0);
                            if ($pp <= 0) $pp = (float) ($promo['suggested_discounted_price'] ?? 0);
                            if ($pp <= 0) continue;
                            $promocoes[] = [
                                'nome' => $promo['name'] ?? 'Sem nome',
                                'tipo' => $promo['type'] ?? '',
                                'preco' => $pp,
                                'meli_pct' => $promo['meli_percentage'] ?? 0,
                                'seller_pct' => $promo['seller_percentage'] ?? 0,
                            ];
                        }
                    }
                }

                $items[] = [
                    'mlb_id' => $mlbId,
                    'status' => $mlbStatus,
                    'price' => $mlbPrice,
                    'listing_type' => $mlbListingType,
                    'estoque' => (int) ($mlb['available_quantity'] ?? 0),
                    'free_shipping' => $mlbFreeShipping,
                    'catalog_listing' => !empty($mlb['catalog_listing']),
                    'logistic_type' => $mlbLogisticType,
                    'comissao_pct' => $comissaoPct,
                    'comissao_valor' => $comissaoValor,
                    'frete' => $frete,
                    'custo' => $custo,
                    'promocoes' => $promocoes,
                ];
            }

            $resultado['ups'][] = [
                'user_product_id' => $upId,
                'name' => $upData['name'] ?? '—',
                'sku' => $sku,
                'cor' => $cor,
                'catalog_product_id' => $upData['catalog_product_id'] ?? null,
                'items' => $items,
            ];
        }

        $this->resultadoRealtime = $resultado;
        $this->buscandoRealtime = false;
        Notification::make()->title("Família carregada: " . count($resultado['ups']) . " UP(s)")->success()->send();
    }

    public function limparRealtime(): void
    {
        $this->resultadoRealtime = null;
        $this->buscaFamiliaRealtime = '';
    }

    public function getGeradoEmProperty(): ?string
    {
        return RelatorioMargemML::orderByDesc('gerado_em')->value('gerado_em')?->format('d/m/Y H:i');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'marketing']) ?? false;
    }
}
