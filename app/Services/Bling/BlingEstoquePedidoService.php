<?php

namespace App\Services\Bling;

use App\Models\PedidoBlingStaging;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BlingEstoquePedidoService
{
    /**
     * Sincroniza estoque de um pedido entre contas.
     *
     * Primary: espelha saldo absoluto Primary → Secondary
     * Secondary: subtrai qtd vendida da Primary, depois espelha Primary → Secondary
     */
    public static function sincronizar(PedidoBlingStaging $pedido): array
    {
        $origem = $pedido->bling_account;
        $destino = $origem === 'primary' ? 'secondary' : 'primary';

        $clientOrigem = new BlingClient($origem);
        $clientPrimary = new BlingClient('primary');
        $clientSecondary = new BlingClient('secondary');

        $itens = $pedido->itens ?? [];
        $log = [];
        $erros = 0;

        foreach ($itens as $item) {
            $sku = $item['codigo'] ?? '';
            $qtdVendida = (int) ($item['quantidade'] ?? 1);

            if (empty($sku)) continue;

            // Identificar estrutura do produto na conta de origem
            $skusParaSincronizar = self::resolverSkus($clientOrigem, $sku, $qtdVendida);

            foreach ($skusParaSincronizar as $skuInfo) {
                $resultado = self::sincronizarSku(
                    $skuInfo['sku'],
                    $skuInfo['quantidade'],
                    $origem,
                    $clientPrimary,
                    $clientSecondary
                );

                $log[] = $resultado['msg'];
                if (!$resultado['success']) $erros++;
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
     * Resolve SKUs: se kit, retorna componentes. Se simples/variação, retorna o próprio.
     */
    private static function resolverSkus(BlingClient $client, string $sku, int $qtdPedido): array
    {
        $produto = $client->getProductBySku($sku);
        if (!$produto) {
            return [['sku' => $sku, 'quantidade' => $qtdPedido]];
        }

        $formato = strtoupper($produto['formato'] ?? 'S');

        // Kit: desmembrar nos componentes
        if (in_array($formato, ['E', 'C'])) {
            $detalhe = $client->getProductById((int) $produto['id']);
            $componentes = $detalhe['estrutura']['componentes'] ?? [];

            if (empty($componentes)) {
                return [['sku' => $sku, 'quantidade' => $qtdPedido]];
            }

            $skus = [];
            foreach ($componentes as $comp) {
                $compId = $comp['produto']['id'] ?? null;
                if (!$compId) continue;

                $compDetalhe = $client->getProductById((int) $compId);
                $compSku = $compDetalhe['codigo'] ?? null;
                if (!$compSku) continue;

                $qtdComponente = (int) ($comp['quantidade'] ?? 1);
                $skus[] = [
                    'sku' => $compSku,
                    'quantidade' => $qtdComponente * $qtdPedido,
                ];
            }

            return !empty($skus) ? $skus : [['sku' => $sku, 'quantidade' => $qtdPedido]];
        }

        // Simples ou Variação: retorna o próprio
        return [['sku' => $sku, 'quantidade' => $qtdPedido]];
    }

    /**
     * Sincroniza um SKU individual entre contas.
     */
    private static function sincronizarSku(
        string $sku,
        int $qtdVendida,
        string $contaOrigem,
        BlingClient $clientPrimary,
        BlingClient $clientSecondary
    ): array {
        // Buscar produto na Primary
        $prodPrimary = $clientPrimary->getProductBySku($sku);
        if (!$prodPrimary) {
            return ['success' => false, 'msg' => "SKU {$sku}: não encontrado na Primary"];
        }
        $prodPrimaryId = (int) $prodPrimary['id'];

        // Buscar saldo atual na Primary
        $saldoPrimary = self::buscarSaldoFisico($clientPrimary, $prodPrimaryId);
        if ($saldoPrimary === null) {
            return ['success' => false, 'msg' => "SKU {$sku}: não foi possível obter saldo na Primary"];
        }

        // Se pedido veio da Secondary, subtrair qtd vendida da Primary
        if ($contaOrigem === 'secondary') {
            $novoSaldo = max(0, $saldoPrimary - $qtdVendida);

            $depositoPrimaryId = self::getDepositoGeral($clientPrimary);
            if (!$depositoPrimaryId) {
                return ['success' => false, 'msg' => "SKU {$sku}: depósito não encontrado na Primary"];
            }

            $res = self::atualizarEstoque($clientPrimary, $prodPrimaryId, $novoSaldo, $depositoPrimaryId);
            if (!$res['success']) {
                return ['success' => false, 'msg' => "SKU {$sku}: erro ao atualizar Primary (HTTP {$res['http_code']})"];
            }

            $saldoPrimary = $novoSaldo;
        }

        // Espelhar saldo da Primary → Secondary
        $prodSecondary = $clientSecondary->getProductBySku($sku);
        if (!$prodSecondary) {
            return ['success' => true, 'msg' => "SKU {$sku}: Primary={$saldoPrimary} (não existe na Secondary)"];
        }
        $prodSecondaryId = (int) $prodSecondary['id'];

        $depositoSecondaryId = self::getDepositoGeral($clientSecondary);
        if (!$depositoSecondaryId) {
            return ['success' => false, 'msg' => "SKU {$sku}: depósito não encontrado na Secondary"];
        }

        $res = self::atualizarEstoque($clientSecondary, $prodSecondaryId, $saldoPrimary, $depositoSecondaryId);
        if (!$res['success']) {
            return ['success' => false, 'msg' => "SKU {$sku}: erro ao espelhar na Secondary (HTTP {$res['http_code']})"];
        }

        $sufixo = $contaOrigem === 'secondary' ? " (subtraiu {$qtdVendida})" : '';
        return ['success' => true, 'msg' => "SKU {$sku}: Primary={$saldoPrimary} → Secondary={$saldoPrimary}{$sufixo}"];
    }

    private static function buscarSaldoFisico(BlingClient $client, int $produtoId): ?int
    {
        $depositoGeralId = self::getDepositoGeral($client);

        $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $produtoId]);
        if (!$res['success'] || empty($res['body']['data'])) {
            $produto = $client->getProductById($produtoId);
            if ($produto) {
                return (int) ($produto['estoque']['saldoFisicoTotal']
                    ?? $produto['estoque']['saldoVirtualTotal'] ?? 0);
            }
            return null;
        }

        $dados = $res['body']['data'][0] ?? null;
        if (!$dados) return null;

        // Buscar saldo apenas do depósito Geral, ignorando outros (ex: Estoque Virtual)
        foreach ($dados['depositos'] ?? [] as $dep) {
            if ($depositoGeralId && (int) ($dep['deposito']['id'] ?? 0) === $depositoGeralId) {
                return (int) ($dep['saldoFisico'] ?? 0);
            }
        }

        // Fallback: primeiro depósito
        $primeiro = $dados['depositos'][0] ?? null;
        return $primeiro ? (int) ($primeiro['saldoFisico'] ?? 0) : 0;
    }

    private static function getDepositoGeral(BlingClient $client): ?int
    {
        $accountKey = spl_object_id($client);
        $cacheKey = "bling_deposito_geral_{$accountKey}";

        $cached = Cache::get($cacheKey);
        if ($cached) return (int) $cached;

        $res = $client->get('/depositos', ['limite' => 100]);
        if (!$res['success']) return null;

        foreach ($res['body']['data'] ?? [] as $d) {
            $nome = strtolower(trim($d['descricao'] ?? ''));
            if (str_contains($nome, 'geral')) {
                Cache::put($cacheKey, $d['id'], now()->addDay());
                return (int) $d['id'];
            }
        }

        // Fallback: primeiro depósito
        $primeiro = $res['body']['data'][0] ?? null;
        if ($primeiro) {
            Cache::put($cacheKey, $primeiro['id'], now()->addDay());
            return (int) $primeiro['id'];
        }

        return null;
    }

    private static function atualizarEstoque(BlingClient $client, int $produtoId, int $quantidade, int $depositoId): array
    {
        $res = $client->post('/estoques', [], [
            'produto' => ['id' => $produtoId],
            'deposito' => ['id' => $depositoId],
            'operacao' => 'B',
            'preco' => 0,
            'custo' => 0,
            'quantidade' => $quantidade,
            'observacoes' => 'Sync automático via pedido',
        ]);

        if (!$res['success'] && in_array((int) ($res['http_code'] ?? 0), [429, 500, 502, 503, 504])) {
            sleep(2);
            $res = $client->post('/estoques', [], [
                'produto' => ['id' => $produtoId],
                'deposito' => ['id' => $depositoId],
                'operacao' => 'B',
                'preco' => 0,
                'custo' => 0,
                'quantidade' => $quantidade,
                'observacoes' => 'Sync automático via pedido (retry)',
            ]);
        }

        return $res;
    }
}
