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
        {--limit=5 : Limite de itens para processar (0 = todos)}
        {--dry-run : Apenas exibe no terminal sem salvar no banco}';

    protected $description = 'Gera relatório de margem dos anúncios ativos no Mercado Livre (promoções, custo, comissão)';

    private MercadoLivreClient $mlClient;
    private MercadoLivrePromotionService $promotionService;
    private BlingClient $blingClient;
    private float $impostoPct;
    private string $accountKey;

    public function handle(): int
    {
        $accountKey = $this->option('account');
        $limit = (int) $this->option('limit');
        $startTime = now();

        $dryRun = $this->option('dry-run');

        $this->info("=== Relatório Margem ML [{$accountKey}] ===");
        $this->info("Início: " . $startTime->format('d/m/Y H:i:s'));
        $this->info("Limite: " . ($limit ?: 'TODOS'));
        if ($dryRun) $this->warn('⚠️  MODO DRY-RUN: nenhum dado será salvo no banco.');

        $this->accountKey = $accountKey;
        $this->mlClient = new MercadoLivreClient($accountKey);
        $this->promotionService = new MercadoLivrePromotionService($accountKey);
        $this->blingClient = new BlingClient($accountKey);

        if (!$this->mlClient->isAuthorized()) {
            $this->error("Conta ML '{$accountKey}' não autorizada.");
            return 1;
        }

        // Imposto do mês atual (baseado na conta)
        $idCnpj = $accountKey === 'secondary' ? 2 : 1;
        $this->impostoPct = $this->getImpostoPct($idCnpj);
        $this->info("Imposto mês atual (CNPJ {$idCnpj}): {$this->impostoPct}%");

        // 1. Buscar MLBs ativos com estoque
        $items = $this->buscarItensAtivos($accountKey, $limit);
        if (empty($items)) {
            $this->warn("Nenhum item ativo com estoque encontrado.");
            return 0;
        }

        $this->info("Itens encontrados: " . count($items));
        $geradoEm = now();

        if (!$dryRun) {
            RelatorioMargemML::where('account_key', $accountKey)->delete();
        }

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        $dryRunResults = [];
        foreach ($items as $itemId) {
            $resultado = $this->processarItem($accountKey, $itemId, $geradoEm, $dryRun);
            if ($dryRun && $resultado) {
                $dryRunResults[] = $resultado;
            }
            $bar->advance();
            sleep(1);
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->newLine();
            $this->info('┌─────────────────────────────────────────────────────────────────────────────────────────────┐');
            $this->info('│  RESULTADOS DRY-RUN                                                                        │');
            $this->info('├─────────────────────────────────────────────────────────────────────────────────────────────┤');
            foreach ($dryRunResults as $r) {
                $margemColor = $r['margem_pct'] >= 15 ? 'info' : ($r['margem_pct'] >= 0 ? 'comment' : 'error');
                $this->newLine();
                $this->line("  <fg=cyan>{$r['mlb_id']}</> | SKU: {$r['sku']} | {$r['titulo']}");
                $this->line("  Preço: R$ {$r['preco']}  |  Custo: R$ {$r['custo']}  |  Frete: R$ {$r['frete']}  |  Free Shipping: {$r['free_shipping']}");
                $this->line("  Comissão: {$r['comissao_pct']}% (R$ {$r['comissao_valor']})  |  Imposto: {$r['imposto_pct']}% (R$ {$r['imposto_valor']})  |  Antecipação: R$ {$r['antecipacao']}");
                $this->{$margemColor}("  ► MARGEM: R$ {$r['margem_valor']} ({$r['margem_pct']}%)");
                if (!empty($r['promocoes'])) {
                    foreach ($r['promocoes'] as $p) {
                        $pColor = ($p['margem_pct'] ?? 0) >= 15 ? 'info' : (($p['margem_pct'] ?? 0) >= 0 ? 'comment' : 'error');
                        $rebateStr = !empty($p['rebate']) ? " | Rebate: {$p['rebate']}" : '';
                        $this->line("    🏷️  {$p['nome']} ({$p['tipo']}/{$p['status']}) → R$ {$p['preco']}{$rebateStr} → Margem: R$ {$p['margem_valor']} ({$p['margem_pct']}%)");
                    }
                }
            }
            $this->newLine();
            $this->info('└─────────────────────────────────────────────────────────────────────────────────────────────┘');
        }

        $total = $dryRun ? count($dryRunResults) : RelatorioMargemML::where('account_key', $accountKey)->count();
        $duration = $startTime->diffInMinutes(now());
        $this->info("Relatório " . ($dryRun ? 'simulado' : 'gerado') . " com {$total} itens.");
        $this->info("Fim: " . now()->format('d/m/Y H:i:s') . " | Duração: {$duration} minutos");

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

    private function processarItem(string $accountKey, string $itemId, $geradoEm, bool $dryRun = false): ?array
    {
        // Buscar dados do item
        $itemResult = $this->mlClient->get("/items/{$itemId}");
        if (!$itemResult['success']) {
            Log::warning("ML Relatório: falha ao buscar item {$itemId}");
            return null;
        }

        $item = $itemResult['body'];
        $preco = (float) ($item['price'] ?? 0);
        $estoque = (int) ($item['available_quantity'] ?? 0);
        $titulo = $item['title'] ?? '';
        $listingType = $item['listing_type_id'] ?? '';
        $categoryId = $item['category_id'] ?? '';

        if ($estoque <= 0 || $preco <= 0) return null;

        // Catálogo
        $catalogProductId = $item['catalog_product_id'] ?? null;
        $isCatalogListing = !empty($item['catalog_listing']);
        $statusMl = $item['status'] ?? null;

        // User Product e Family
        $userProductId = $item['user_product_id'] ?? null;
        $familyName = $item['family_name'] ?? null;
        $familyId = null;
        $cor = null;

        if ($userProductId) {
            sleep(1);
            $upResult = $this->mlClient->get("/user-products/{$userProductId}");
            if ($upResult['success']) {
                $upBody = $upResult['body'];
                $familyId = isset($upBody['family_id']) ? (string) $upBody['family_id'] : null;
                $familyName = $familyName ?? ($upBody['family_name'] ?? null);
                foreach ($upBody['attributes'] ?? [] as $attr) {
                    if ($attr['id'] === 'COLOR') {
                        $cor = $attr['values'][0]['name'] ?? null;
                        break;
                    }
                }
            }
        }

        // item_relations: buscar MLB pai (se é catálogo) ou MLB catálogo (se vinculado)
        $itemRelationId = null;
        $relations = $item['item_relations'] ?? [];
        if (!empty($relations) && isset($relations[0]['id'])) {
            $itemRelationId = $relations[0]['id'];
        }

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

        // Imposto incide sobre o preço de venda (nota fiscal é emitida pelo valor do produto)
        $impostoValor = round($preco * $this->impostoPct / 100, 2);

        // Antecipação de parcelas
        $antecipacaoPct = $this->getAntecipacaoPct();
        $antecipacaoValor = round($preco * $antecipacaoPct / 100, 2);

        // Margem = Preço - Comissão - Frete - Antecipação - Imposto - Custo
        $margemValor = round($preco - $comissaoValor - $frete - $antecipacaoValor - $impostoValor - $custo, 2);
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
                // Determinar preço: price > 0, senão max_discounted_price, senão suggested
                $pp = (float) ($promo['price'] ?? 0);
                if ($pp <= 0) $pp = (float) ($promo['max_discounted_price'] ?? 0);
                if ($pp <= 0) $pp = (float) ($promo['suggested_discounted_price'] ?? 0);
                $pp = $pp > 0 ? $pp : null;

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
                    $impPromo = round($pp * $this->impostoPct / 100, 2);
                    $antPromo = round($pp * $antecipacaoPct / 100, 2);
                    $rebatePromo = round($pp * (float) ($promo['meli_percentage'] ?? 0) / 100, 2);
                    $promoMargem = round($pp - $comPromoValor - $frete - $antPromo - $impPromo - $custo + $rebatePromo, 2);
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
                    'rebate_valor' => $rebatePromo ?? 0,
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

        $dados = [
            'account_key' => $accountKey,
            'mlb_id' => $itemId,
            'sku' => $sku,
            'titulo' => mb_substr($titulo, 0, 255),
            'listing_type' => $listingType,
            'catalog_product_id' => $catalogProductId,
            'is_catalog_listing' => $isCatalogListing,
            'item_relation_id' => $itemRelationId,
            'user_product_id' => $userProductId,
            'family_id' => $familyId,
            'family_name' => $familyName ? mb_substr($familyName, 0, 255) : null,
            'cor' => $cor,
            'status_ml' => $statusMl,
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
        ];

        if ($dryRun) {
            return [
                'mlb_id' => $itemId,
                'sku' => $sku ?? '—',
                'titulo' => mb_substr($titulo, 0, 50),
                'preco' => number_format($preco, 2, ',', '.'),
                'custo' => number_format($custo, 2, ',', '.'),
                'frete' => number_format($frete, 2, ',', '.'),
                'free_shipping' => ($item['shipping']['free_shipping'] ?? false) ? 'SIM' : 'NÃO',
                'comissao_pct' => number_format($comissaoPct, 1),
                'comissao_valor' => number_format($comissaoValor, 2, ',', '.'),
                'imposto_pct' => number_format($this->impostoPct, 1),
                'imposto_valor' => number_format($impostoValor, 2, ',', '.'),
                'antecipacao' => number_format($antecipacaoValor, 2, ',', '.'),
                'margem_valor' => number_format($margemValor, 2, ',', '.'),
                'margem_pct' => number_format($margemPct, 1),
                'promocoes' => collect($promocoes)->map(fn ($p) => [
                    'nome' => $p['nome'],
                    'tipo' => $p['tipo'],
                    'status' => $p['status'],
                    'preco' => $p['preco'] ? number_format($p['preco'], 2, ',', '.') : '—',
                    'rebate' => ($p['meli_pct'] ?? 0) > 0 ? "+{$p['meli_pct']}% (R$ " . number_format($p['rebate_valor'] ?? 0, 2, ',', '.') . ')' : '',
                    'margem_valor' => $p['margem_valor'] !== null ? number_format($p['margem_valor'], 2, ',', '.') : '—',
                    'margem_pct' => $p['margem_pct'] !== null ? number_format($p['margem_pct'], 1) : '—',
                ])->toArray(),
            ];
        }

        RelatorioMargemML::create($dados);
        return null;
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
        // Vendedor só paga frete se free_shipping=true (ele ativou frete grátis)
        $freeShipping = $item['shipping']['free_shipping'] ?? false;
        if (!$freeShipping) return 0;

        // Buscar custo real via shipping_options (custo que o ML cobra do vendedor)
        sleep(1);
        $result = $this->mlClient->get("/items/{$itemId}/shipping_options", ['zip_code' => '01310100']);

        if ($result['success'] && !empty($result['body']['options'])) {
            // Usar a PRIMEIRA opção (mais barata/padrão) pois é a que o ML cobra na média
            // list_cost = custo total do frete; cost = quanto o comprador paga (0 se grátis)
            $opt = $result['body']['options'][0];
            $listCost = (float) ($opt['list_cost'] ?? 0);
            if ($listCost > 0) return round($listCost, 2);
        }

        // Fallback: tentar endpoint /shipping_options/free
        $userId = MercadoLivreToken::where('account_key', $this->accountKey)
            ->value('user_id');

        if ($userId) {
            sleep(1);
            $freeResult = $this->mlClient->get("/users/{$userId}/shipping_options/free", [
                'item_id' => $itemId,
            ]);
            if ($freeResult['success']) {
                $cost = $this->extrairMaiorValor($freeResult['body']);
                if ($cost > 0) return round($cost, 2);
            }
        }

        Log::warning("ML Relatório: frete zerado para {$itemId} (free_shipping=true mas sem dados)");
        return 0;
    }

    private function extrairMaiorValor(mixed $data, float $max = 0): float
    {
        if (!is_array($data)) return $max;

        foreach (['list_cost', 'cost', 'amount', 'base_cost'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $max = max($max, (float) $data[$key]);
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $max = $this->extrairMaiorValor($value, $max);
            }
        }

        return $max;
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

    private function getAntecipacaoPct(): float
    {
        $canal = \App\Models\CanalVenda::where('nome_canal', 'Mercadolivre')->where('ativo', true)->first();
        return (float) ($canal->percentual_antecipacao ?? 0);
    }
}
