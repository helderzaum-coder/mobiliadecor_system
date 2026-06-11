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

        // Frete (estimado pela tabela do sistema)
        $frete = $this->estimarFrete($preco, $item);

        // Imposto (nota tipo produto = sobre preço - frete)
        $baseImposto = max(0, $preco - $frete);
        $impostoValor = round($baseImposto * $this->impostoPct / 100, 2);

        // Margem
        $recebe = $preco - $comissaoValor - $frete;
        $margemValor = round($recebe - $custo - $impostoValor, 2);
        $margemPct = $preco > 0 ? round(($margemValor / $preco) * 100, 2) : 0;

        // Promoções do item
        $promoResult = $this->promotionService->buscarPromocoesParaItem($itemId);
        $promocoes = [];
        $precoPromocional = null;
        $margemPromocional = null;
        $margemPromocionalPct = null;

        if ($promoResult['success'] && !empty($promoResult['promotions'])) {
            foreach ($promoResult['promotions'] as $promo) {
                $promocoes[] = [
                    'nome' => $promo['name'] ?? 'Sem nome',
                    'tipo' => $promo['type'] ?? '',
                    'preco' => $promo['price'] ?? null,
                    'meli_pct' => $promo['meli_percentage'] ?? 0,
                    'seller_pct' => $promo['seller_percentage'] ?? 0,
                ];

                // Usar o preço da promoção para calcular margem promocional
                $pp = $promo['price'] ?? null;
                if ($pp && ($precoPromocional === null || $pp < $precoPromocional)) {
                    $precoPromocional = (float) $pp;
                }
            }

            if ($precoPromocional && $precoPromocional < $preco) {
                // Recalcular comissão para preço promocional
                $comPromo = $this->promotionService->buscarComissaoParaPreco(
                    $precoPromocional, $listingType, $categoryId,
                    $item['shipping']['logistic_type'] ?? 'xd_drop_off',
                    $item['shipping']['mode'] ?? 'me2'
                );
                $comPromoValor = $comPromo['valor'] ?? round($precoPromocional * $comissaoPct / 100, 2);
                $baseImpPromo = max(0, $precoPromocional - $frete);
                $impPromo = round($baseImpPromo * $this->impostoPct / 100, 2);
                $recebePromo = $precoPromocional - $comPromoValor - $frete;
                $margemPromocional = round($recebePromo - $custo - $impPromo, 2);
                $margemPromocionalPct = $precoPromocional > 0
                    ? round(($margemPromocional / $precoPromocional) * 100, 2) : 0;
            }
        }

        sleep(1); // Rate limit entre chamadas de promoção

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

    private function estimarFrete(float $preco, array $item): float
    {
        // Se o item não tem frete grátis, frete = 0 para o vendedor
        $freeShipping = $item['shipping']['free_shipping'] ?? false;
        if (!$freeShipping) return 0;

        // Estimar peso via shipping tags ou usar peso padrão
        // Usar tabela simplificada baseada no preço (faixa mais comum)
        $peso = 5.0; // peso médio padrão para estimativa
        $tags = $item['shipping']['tags'] ?? [];
        foreach ($tags as $tag) {
            if (str_contains($tag, 'mandatory_free_shipping')) {
                // ML paga parte, mas vendedor paga diferença
                break;
            }
        }

        // Buscar dimensões do item se disponível
        if (!empty($item['shipping']['dimensions'])) {
            $dims = $item['shipping']['dimensions'];
            // formato: "33.0x50.0x12.0,1100" (AxLxC, peso em gramas)
            if (is_string($dims)) {
                $parts = explode(',', $dims);
                if (isset($parts[1])) {
                    $peso = ((float) $parts[1]) / 1000;
                }
            }
        }

        // Usar tabela de frete simplificada do sistema
        return $this->calcularFreteTabela($preco, $peso);
    }

    private function calcularFreteTabela(float $preco, float $peso): float
    {
        // Tabela simplificada ME2 (mesma do CalculadoraML)
        $tabela = [
            5 => [6.55, 8.35, 9.75, 18.45, 21.55, 24.65, 27.75, 30.75],
            10 => [7.05, 9.55, 10.95, 41.25, 48.05, 54.95, 61.75, 68.65],
            20 => [7.45, 10.55, 11.95, 54.75, 63.85, 72.95, 82.05, 91.15],
            30 => [7.75, 11.15, 12.35, 65.95, 75.45, 85.55, 96.25, 106.95],
        ];

        $faixasPreco = [
            [0, 18.99], [19, 48.99], [49, 78.99], [79, 99.99],
            [100, 119.99], [120, 149.99], [150, 199.99], [200, 999999],
        ];

        // Determinar faixa de peso
        $pesoKey = 5;
        foreach ([5, 10, 20, 30] as $k) {
            if ($peso <= $k) { $pesoKey = $k; break; }
            $pesoKey = $k;
        }

        $valores = $tabela[$pesoKey];

        foreach ($faixasPreco as $i => [$min, $max]) {
            if ($preco >= $min && $preco <= $max) {
                return $valores[$i] ?? end($valores);
            }
        }

        return end($valores);
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
