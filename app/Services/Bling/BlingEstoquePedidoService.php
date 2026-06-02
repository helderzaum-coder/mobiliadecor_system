<?php

namespace App\Services\Bling;

use App\Models\PedidoBlingStaging;
use App\Models\ProdutoEstoque;
use App\Services\EstoqueService;
use Illuminate\Support\Facades\Log;

class BlingEstoquePedidoService
{
    /**
     * Sincroniza estoque de um pedido.
     *
     * Novo fluxo: o sistema é a fonte da verdade.
     * - Desconta do estoque interno (desmembrando kits)
     * - O EstoqueService dispara o saldo para AMBOS os Blings automaticamente
     */
    public static function sincronizar(PedidoBlingStaging $pedido): array
    {
        $origem = $pedido->bling_account;
        $itens = $pedido->itens ?? [];
        $log = [];
        $erros = 0;

        foreach ($itens as $item) {
            $sku = $item['codigo'] ?? '';
            $qtd = (int) ($item['quantidade'] ?? 1);
            if (empty($sku)) continue;

            // Resolver SKUs (desmembrar kits usando cadastro interno)
            $skusParaDescontar = self::resolverSkusInterno($sku, $qtd, $origem);

            foreach ($skusParaDescontar as $skuInfo) {
                $prod = ProdutoEstoque::where('sku', $skuInfo['sku'])->where('ativo', true)->first();
                $tipoEstoque = ($prod && $prod->saldo_fisico > 0) ? 'fisico' : 'virtual';
                $contaNome = $origem === 'primary' ? 'Mobília Decor' : 'HES Móveis';
                $res = EstoqueService::saida(
                    $skuInfo['sku'],
                    $skuInfo['quantidade'],
                    "venda_{$origem}",
                    "Venda: #{$pedido->numero_pedido} - SKU: {$sku} - Conta: {$contaNome}",
                    null,
                    true,
                    $tipoEstoque
                );

                if ($res['success']) {
                    $log[] = "{$skuInfo['sku']}: -{$skuInfo['quantidade']} → saldo {$res['saldo']}";
                } else {
                    $erros++;
                    $log[] = "{$skuInfo['sku']}: ERRO - " . ($res['erro'] ?? '?');
                }
            }
        }

        if ($erros === 0) {
            $pedido->update(['estoque_sincronizado' => true]);
        }

        Log::info("BlingEstoquePedido: Pedido #{$pedido->numero_pedido} ({$origem}) sincronizado", [
            'erros' => $erros,
            'log' => $log,
        ]);

        return [
            'success' => $erros === 0,
            'erros' => $erros,
            'log' => $log,
        ];
    }

    /**
     * Resolve SKUs usando o cadastro interno.
     * Se é kit, retorna os componentes. Se simples, retorna o próprio.
     * Se não encontrar no cadastro interno, tenta resolver via API do Bling.
     */
    private static function resolverSkusInterno(string $sku, int $qtdPedido, string $account): array
    {
        $produto = ProdutoEstoque::where('sku', $sku)->where('ativo', true)->first();

        if ($produto && $produto->isKit()) {
            $componentes = $produto->componentes;
            if ($componentes->isNotEmpty()) {
                return $componentes->map(fn ($comp) => [
                    'sku' => $comp->sku,
                    'quantidade' => $comp->pivot->quantidade * $qtdPedido,
                ])->toArray();
            }
        }

        // Se encontrou como simples, ou não é kit, retorna o próprio
        if ($produto) {
            return [['sku' => $sku, 'quantidade' => $qtdPedido]];
        }

        // Não encontrou no cadastro interno — tentar resolver via Bling e cadastrar
        return self::resolverViaBling($sku, $qtdPedido, $account);
    }

    /**
     * Fallback: resolve via API do Bling e cadastra o produto no sistema.
     */
    private static function resolverViaBling(string $sku, int $qtdPedido, string $account): array
    {
        $client = new BlingClient($account);
        $produto = $client->getProductBySku($sku);

        if (!$produto) {
            // Cadastrar mesmo sem encontrar no Bling (para não perder a movimentação)
            ProdutoEstoque::firstOrCreate(
                ['sku' => $sku],
                ['nome' => "SKU {$sku} (auto)", 'formato' => 'S', 'saldo' => 0]
            );
            return [['sku' => $sku, 'quantidade' => $qtdPedido]];
        }

        $formato = strtoupper($produto['formato'] ?? 'S');
        $nome = $produto['nome'] ?? $sku;
        $observacoes = $produto['observacoes'] ?? null;

        // Cadastrar no sistema
        $produtoEstoque = ProdutoEstoque::firstOrCreate(
            ['sku' => $sku],
            ['nome' => $nome, 'observacoes' => $observacoes, 'formato' => $formato, 'saldo' => 0]
        );
        // Atualizar observações se veio da API e estava vazio
        if ($observacoes && !$produtoEstoque->observacoes) {
            $produtoEstoque->update(['observacoes' => $observacoes]);
        }

        // Se é kit, resolver componentes
        if (in_array($formato, ['E', 'C'])) {
            $detalhe = $client->getProductById((int) $produto['id']);
            $componentes = $detalhe['estrutura']['componentes'] ?? [];

            if (!empty($componentes)) {
                $skus = [];
                $syncData = [];

                foreach ($componentes as $comp) {
                    $compId = $comp['produto']['id'] ?? null;
                    if (!$compId) continue;

                    $compDetalhe = $client->getProductById((int) $compId);
                    $compSku = $compDetalhe['codigo'] ?? null;
                    if (!$compSku) continue;

                    $qtdComponente = (int) ($comp['quantidade'] ?? 1);

                    // Cadastrar componente
                    $compObs = $compDetalhe['observacoes'] ?? null;
                    $compEstoque = ProdutoEstoque::firstOrCreate(
                        ['sku' => $compSku],
                        ['nome' => $compDetalhe['nome'] ?? $compSku, 'observacoes' => $compObs, 'formato' => 'S', 'saldo' => 0]
                    );
                    if ($compObs && !$compEstoque->observacoes) {
                        $compEstoque->update(['observacoes' => $compObs]);
                    }

                    $syncData[$compEstoque->id] = ['quantidade' => $qtdComponente];
                    $skus[] = ['sku' => $compSku, 'quantidade' => $qtdComponente * $qtdPedido];
                }

                if (!empty($syncData)) {
                    $produtoEstoque->componentes()->sync($syncData);
                }

                if (!empty($skus)) {
                    return $skus;
                }
            }
        }

        return [['sku' => $sku, 'quantidade' => $qtdPedido]];
    }
}
