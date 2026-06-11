<?php

namespace App\Console\Commands;

use App\Models\ImpostoMensal;
use App\Models\MercadoLivreToken;
use App\Models\RelatorioMargemML;
use App\Services\Bling\BlingClient;
use App\Services\MercadoLivre\MercadoLivreClient;
use App\Services\MercadoLivre\MercadoLivrePromotionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MercadoLivreRelatorioMargem extends Command
{
    protected $signature = 'ml:relatorio-margem
        {--account=primary : Conta ML (primary/secondary)}
        {--limit=5 : Limite de itens para processar (0 = todos)}';

    protected $description = 'Gera relatório de margem dos anúncios ativos no Mercado Livre (promoções, custo, comissão)';

    private MercadoLivreClient $mlClient;
    private MercadoLivrePromotionService $promotionService;
    private BlingClient $blingClient;
    private float $impostoPct;

    public function handle(): int
    {
        $accountKey = $this->option('account');
        $limit = (int) $this->option('limit');

        $this->info("=== Relatório Margem ML [{$accountKey}] ===");
        $this->info("Limite: " . ($limit ?: 'TODOS'));

        $this->mlClient = new MercadoLivreClient($accountKey);
        $this->promotionService = new MercadoLivrePromotionService($accountKey);
        $this->blingClient = new BlingClient($accountKey);

        if (!$this->mlClient->isAuthorized()) {
            $this->error("Conta ML '{$accountKey}' não autorizada.");
            return 1;
        }

        // Imposto do mês atual (CNPJ 1 = HES Decor por padrão)
        $this->impostoPct = $this->getImpostoPct(1);
        $this->info("Imposto mês atual: {$this->impostoPct}%");

        // 1. Buscar MLBs ativos com estoque
        $items = $this->buscarItensAtivos($accountKey, $limit);
        if (empty($items)) {
            $this->warn("Nenhum item ativo com estoque encontrado.");
            return 0;
        }

        $this->info("Itens encontrados: " . count($items));
        $geradoEm = now();

        // Limpar relatório anterior desta conta
        RelatorioMargemML::where('account_key', $accountKey)->delete();

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $itemId) {
            $this->processarItem($accountKey, $itemId, $geradoEm);
            $bar->advance();
            sleep(1); // Rate limit API
        }

        $bar->finish();
        $this->newLine(2);

        $total = RelatorioMargemML::where('account_key', $accountKey)->count();
        $this->info("Relatório gerado com {$total} itens.");

        return 0;
    }

    private function buscarItensAtivos(string $accountKey, int $limit): array
    {
        $tokenModel = MercadoLivreToken::where('account_key', $accountKey)->first();
        $userId = $tokenModel?->user_id ?? config("mercadolivre.accounts.{$accountKey}.user_id");

        if (!$userId) {
            $this->error("User ID não configurado para '{$accountKey}'");
            return [];
        }

        $items = [];
        $offset = 0;
        $pageSize = 50;

        do {
            $result = $this->mlClient->get("/users/{$userId}/items/search", [
                'status' => 'active',
                'offset' => $offset,
                'limit' => $pageSize,
            ]);

            if (!$result['success']) {
                $this->error("Erro ao buscar itens: HTTP " . ($result['http_code'] ?? '?'));
                break;
            }

            $pageItems = $result['body']['results'] ?? [];
            $items = array_merge($items, $pageItems);
            $total = $result['body']['paging']['total'] ?? 0;
            $offset += $pageSize;

            if ($limit > 0 && count($items) >= $limit) {
                $items = array_slice($items, 0, $limit);
                break;
            }

            sleep(1);
        } while ($offset < $total);

        return $items;
    }

    private function processarItem(string $accountKey, string $itemId, $geradoEm): void
    {
        // Buscar dados do item
        $itemResult = $this->mlClient->get("/items/{$itemId}");
        if (!$itemResult['success']) {
            Log::warning("ML Relatório: falha ao buscar item {$itemId}");
            return;
        }

        $item = $itemResult['body'];
        $preco = (float) ($item['price'] ?? 0);
        $estoque = (int) ($item['available_quantity'] ?? 0);
        $titulo = $item['title'] ?? '';
        $listingType = $item['listing_type_id'] ?? '';
        $categoryId = $item['category_id'] ?? '';

        if ($estoque <= 0 || $preco <= 0) return;

        // Extrair SKU
        $sku = $this->extrairSku($item);

        // Buscar custo no Bling
        $custo = 0;
        if ($sku) {
            $custo = $this->buscarCustoBling($sku);
        }

        // Comissão via API listing_prices
        $comissaoData = $this->promotionService->buscarComissaoParaPreco(
            $preco, $listingType, $categoryId,
            $item['shipping']['logistic_type'] ?? 'xd_drop_off',
            $item['shipping']['mode'] ?? 'me2'
        );
        $comissaoPct = $comissaoData['percent'] ?? $this->comissaoFallback($listingType);
        $comissaoValor = $comissaoData['valor'] ?? round($preco * $comissaoPct / 100, 2);

        // Frete real cobrado pelo ML via shipping_options
        $frete = $this->buscarFreteReal($itemId, $item);

        // Imposto (nota tipo produto = sobre preço - frete)
        $baseImposto = max(0, $preco - $frete);
        $impostoValor = round($baseImposto * $this->impostoPct / 100, 2);

        // Margem
        $recebe = $preco - $comissaoValor - $frete;
        $margemValor = round($recebe - $custo - $impostoValor, 2);
        $margemPct = $preco > 0 ? round(($margemValor / $preco) * 100, 2) : 0;

        // Promoções do item — buscar TODAS
        sleep(1);
        $promoResult = $this->promotionService->buscarPromocoesParaItem($itemId);
        $promocoes = [];
        $precoPromocional = null;
        $margemPromocional = null;
        $margemPromocionalPct = null;

        if ($promoResult['success'] && !empty($promoResult['promotions'])) {
            foreach ($promoResult['promotions'] as $promo) {
                $pp = isset($promo['price']) ? (float) $promo['price'] : null;
                $promoMargem = null;
                $promoMargemPct = null;

                // Calcular margem individual para cada promoção
                if ($pp && $pp > 0) {
                    $comPromo = $this->promotionService->buscarComissaoParaPreco(
                        $pp, $listingType, $categoryId,
                        $item['shipping']['logistic_type'] ?? 'xd_drop_off',
                        $item['shipping']['mode'] ?? 'me2'
                    );
                    $comPromoValor = $comPromo['valor'] ?? round($pp * $comissaoPct / 100, 2);
                    $baseImpPromo = max(0, $pp - $frete);
                    $impPromo = round($baseImpPromo * $this->impostoPct / 100, 2);
                    $recebePromo = $pp - $comPromoValor - $frete;
                    $promoMargem = round($recebePromo - $custo - $impPromo, 2);
                    $promoMargemPct = $pp > 0 ? round(($promoMargem / $pp) * 100, 2) : 0;

                    sleep(1); // Rate limit
                }

                $promocoes[] = [
                    'nome' => $promo['name'] ?? 'Sem nome',
                    'tipo' => $promo['type'] ?? '',
                    'status' => $promo['status'] ?? '',
                    'preco' => $pp,
                    'meli_pct' => $promo['meli_percentage'] ?? 0,
                    'seller_pct' => $promo['seller_percentage'] ?? 0,
                    'margem_valor' => $promoMargem,
                    'margem_pct' => $promoMargemPct,
                    'inicio' => $promo['start_date'] ?? null,
                    'fim' => $promo['finish_date'] ?? null,
                ];

                // Guardar o menor preço promocional como principal
                if ($pp && ($precoPromocional === null || $pp < $precoPromocional)) {
                    $precoPromocional = $pp;
                    $margemPromocional = $promoMargem;
                    $margemPromocionalPct = $promoMargemPct;
                }
            }
        }

        RelatorioMargemML::create([
            'account_key' => $accountKey,
            'mlb_id' => $itemId,
            'sku' => $sku,
            'titulo' => mb_substr($titulo, 0, 255),
            'listing_type' => $listingType,
            'preco_venda' => $preco,
            'custo_produto' => $custo,
            'estoque' => $estoque,

            'comissao_pct' => $comissaoPct,
            'comissao_valor' => $comissaoValor,
            'frete' => $frete,
            'imposto_pct' => $this->impostoPct,
            'imposto_valor' => $impostoValor,
            'margem_valor' => $margemValor,
            'margem_pct' => $margemPct,
            'promocoes' => !empty($promocoes) ? $promocoes : null,
            'preco_promocional' => $precoPromocional,
            'margem_promocional' => $margemPromocional,
            'margem_promocional_pct' => $margemPromocionalPct,
            'gerado_em' => $geradoEm,
        ]);
    }

    private function extrairSku(array $item): ?string
    {
        $sku = $item['seller_custom_field'] ?? null;

        if (!$sku) {
            foreach ($item['attributes'] ?? [] as $attr) {
                if (($attr['id'] ?? '') === 'SELLER_SKU') {
                    $sku = $attr['value_name'] ?? null;
                    break;
                }
            }
        }

        if (!$sku && !empty($item['variations'])) {
            $sku = $item['variations'][0]['seller_custom_field'] ?? null;
        }

        // SKU composto: "10061880__4286723" → "10061880"
        if ($sku && str_contains($sku, '__')) {
            $sku = explode('__', $sku)[0];
        }

        return $sku;
    }

    private function buscarCustoBling(string $sku): float
    {
        try {
            $produto = $this->blingClient->getProductBySku($sku);
            $custo = (float) ($produto['precoCusto'] ?? 0);

            if ($custo <= 0 && !empty($produto['id'])) {
                $detalhe = $this->blingClient->getProductById((int) $produto['id']);
                $custo = (float) ($detalhe['precoCusto'] ?? 0);
            }

            return $custo;
        } catch (\Throwable $e) {
            Log::warning("ML Relatório: erro Bling SKU {$sku}: " . $e->getMessage());
            return 0;
        }
    }

    private function buscarFreteReal(string $itemId, array $item): float
    {
        // Se não tem frete grátis, vendedor não paga frete
        $freeShipping = $item['shipping']['free_shipping'] ?? false;
        if (!$freeShipping) return 0;

        // Buscar via shipping_options do ML
        sleep(1);
        $result = $this->mlClient->get("/items/{$itemId}/shipping_options", ['zip_code' => '01310100']);

        if (!$result['success']) {
            Log::warning("ML Relatório: falha shipping_options {$itemId}");
            return 0;
        }

        $options = $result['body']['options'] ?? [];
        $maiorFrete = 0;

        foreach ($options as $opt) {
            $listCost = (float) ($opt['list_cost'] ?? 0);
            $buyerCost = (float) ($opt['cost'] ?? 0);
            // Custo do vendedor = list_cost - o que o comprador paga
            $cost = max(0, $listCost - $buyerCost);
            if ($cost <= 0 && !empty($opt['free_shipping'])) {
                $cost = $listCost;
            }
            $maiorFrete = max($maiorFrete, $cost);
        }

        return round($maiorFrete, 2);
    }


    private function comissaoFallback(string $listingType): float
    {
        return match ($listingType) {
            'gold_pro' => 16.5,
            'gold_special' => 11.5,
            default => 11.5,
        };
    }

    private function getImpostoPct(int $idCnpj): float
    {
        $imposto = ImpostoMensal::where('id_cnpj', $idCnpj)
            ->where('mes_referencia', now()->month)
            ->where('ano_referencia', now()->year)
            ->first();

        if (!$imposto) {
            $imposto = ImpostoMensal::where('id_cnpj', $idCnpj)
                ->orderByDesc('ano_referencia')
                ->orderByDesc('mes_referencia')
                ->first();
        }

        return (float) ($imposto->percentual_imposto ?? 0);
    }
}
