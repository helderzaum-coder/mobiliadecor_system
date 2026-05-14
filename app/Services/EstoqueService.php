<?php

namespace App\Services;

use App\Models\MovimentacaoEstoque;
use App\Models\ProdutoEstoque;
use App\Jobs\SyncEstoqueBlingJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EstoqueService
{
    /**
     * Entrada de estoque.
     */
    public static function entrada(
        string $sku,
        int $quantidade,
        string $origem = 'manual',
        ?string $referencia = null,
        ?int $userId = null,
        bool $syncBling = true
    ): array {
        return self::movimentar($sku, 'entrada', $quantidade, $origem, $referencia, $userId, $syncBling);
    }

    /**
     * Saída de estoque.
     */
    public static function saida(
        string $sku,
        int $quantidade,
        string $origem = 'manual',
        ?string $referencia = null,
        ?int $userId = null,
        bool $syncBling = true
    ): array {
        return self::movimentar($sku, 'saida', $quantidade, $origem, $referencia, $userId, $syncBling);
    }

    /**
     * Balanço (seta saldo absoluto).
     */
    public static function balanco(
        string $sku,
        int $novoSaldo,
        string $origem = 'manual',
        ?string $referencia = null,
        ?int $userId = null,
        bool $syncBling = true
    ): array {
        $produto = ProdutoEstoque::where('sku', $sku)->where('ativo', true)->first();
        if (!$produto) {
            return ['success' => false, 'erro' => "SKU {$sku} não encontrado ou inativo"];
        }

        $saldoAnterior = $produto->saldo;
        $quantidade = abs($novoSaldo - $saldoAnterior);

        if ($novoSaldo === $saldoAnterior) {
            return ['success' => true, 'saldo' => $saldoAnterior, 'msg' => 'Saldo já está no valor informado'];
        }

        DB::transaction(function () use ($produto, $novoSaldo, $saldoAnterior, $quantidade, $origem, $referencia, $userId) {
            $produto->update(['saldo' => $novoSaldo]);

            MovimentacaoEstoque::create([
                'produto_estoque_id' => $produto->id,
                'tipo' => 'balanco',
                'quantidade' => $quantidade,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $novoSaldo,
                'origem' => $origem,
                'referencia' => $referencia,
                'user_id' => $userId,
            ]);
        });

        if ($syncBling) {
            $obs = "Balanço (={$novoSaldo})";
            if ($referencia) $obs .= " - {$referencia}";
            SyncEstoqueBlingJob::dispatch($produto->sku, $novoSaldo, $obs, 'B');
        }

        return ['success' => true, 'saldo' => $novoSaldo];
    }

    /**
     * Processa saída por venda (desmembra kits automaticamente).
     */
    public static function saidaPorVenda(array $itens, string $contaOrigem, ?string $referencia = null): array
    {
        $resultados = [];
        $origem = "venda_{$contaOrigem}";

        foreach ($itens as $item) {
            $sku = $item['sku'] ?? $item['codigo'] ?? '';
            $qtd = (int) ($item['quantidade'] ?? 1);
            if (empty($sku)) continue;

            $produto = ProdutoEstoque::where('sku', $sku)->where('ativo', true)->first();
            if (!$produto) {
                $resultados[] = ['sku' => $sku, 'success' => false, 'msg' => 'Não encontrado no sistema'];
                continue;
            }

            // Se é kit, descontar dos componentes
            if ($produto->isKit()) {
                foreach ($produto->componentes as $comp) {
                    $qtdComp = $comp->pivot->quantidade * $qtd;
                    $res = self::saida($comp->sku, $qtdComp, $origem, $referencia);
                    $resultados[] = array_merge($res, ['sku' => $comp->sku, 'kit_de' => $sku]);
                }
            } else {
                $res = self::saida($sku, $qtd, $origem, $referencia);
                $resultados[] = array_merge($res, ['sku' => $sku]);
            }
        }

        return $resultados;
    }

    /**
     * Movimentação genérica (entrada ou saída).
     */
    private static function movimentar(
        string $sku,
        string $tipo,
        int $quantidade,
        string $origem,
        ?string $referencia,
        ?int $userId,
        bool $syncBling
    ): array {
        $produto = ProdutoEstoque::where('sku', $sku)->where('ativo', true)->first();
        if (!$produto) {
            return ['success' => false, 'erro' => "SKU {$sku} não encontrado ou inativo"];
        }

        $saldoAnterior = $produto->saldo;
        $saldoPosterior = $tipo === 'entrada'
            ? $saldoAnterior + $quantidade
            : max(0, $saldoAnterior - $quantidade);

        DB::transaction(function () use ($produto, $tipo, $quantidade, $saldoAnterior, $saldoPosterior, $origem, $referencia, $userId) {
            $produto->update(['saldo' => $saldoPosterior]);

            MovimentacaoEstoque::create([
                'produto_estoque_id' => $produto->id,
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'origem' => $origem,
                'referencia' => $referencia,
                'user_id' => $userId,
            ]);
        });

        if ($syncBling) {
            $obs = $tipo === 'entrada' ? "Entrada (+{$quantidade})" : "Saída (-{$quantidade})";
            if ($referencia) $obs .= " - {$referencia}";
            $opBling = $tipo === 'entrada' ? 'E' : 'S';
            SyncEstoqueBlingJob::dispatch($produto->sku, $quantidade, $obs, $opBling);
        }

        Log::info("Estoque: {$tipo} SKU {$sku} qtd={$quantidade} | {$saldoAnterior} → {$saldoPosterior} | {$origem}");

        return ['success' => true, 'saldo' => $saldoPosterior];
    }
}
