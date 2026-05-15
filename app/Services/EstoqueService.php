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
        bool $syncBling = true,
        string $tipoEstoque = 'virtual'
    ): array {
        return self::movimentar($sku, 'entrada', $quantidade, $origem, $referencia, $userId, $syncBling, $tipoEstoque);
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
        bool $syncBling = true,
        string $tipoEstoque = 'virtual'
    ): array {
        return self::movimentar($sku, 'saida', $quantidade, $origem, $referencia, $userId, $syncBling, $tipoEstoque);
    }

    /**
     * Balanço (seta saldo absoluto de um tipo específico).
     */
    public static function balanco(
        string $sku,
        int $novoSaldo,
        string $origem = 'manual',
        ?string $referencia = null,
        ?int $userId = null,
        bool $syncBling = true,
        string $tipoEstoque = 'virtual'
    ): array {
        $produto = ProdutoEstoque::where('sku', $sku)->where('ativo', true)->first();
        if (!$produto) {
            return ['success' => false, 'erro' => "SKU {$sku} não encontrado ou inativo"];
        }

        $campoSaldo = $tipoEstoque === 'fisico' ? 'saldo_fisico' : 'saldo_virtual';
        $saldoAnteriorTipo = $produto->{$campoSaldo};

        if ($novoSaldo === $saldoAnteriorTipo) {
            return ['success' => true, 'saldo' => $produto->saldo, 'msg' => 'Saldo já está no valor informado'];
        }

        $quantidade = abs($novoSaldo - $saldoAnteriorTipo);
        $saldoAnteriorTotal = $produto->saldo;

        DB::transaction(function () use ($produto, $novoSaldo, $saldoAnteriorTotal, $saldoAnteriorTipo, $quantidade, $campoSaldo, $tipoEstoque, $origem, $referencia, $userId) {
            $produto->{$campoSaldo} = $novoSaldo;
            $produto->recalcularSaldo();
            $produto->save();

            MovimentacaoEstoque::create([
                'produto_estoque_id' => $produto->id,
                'tipo' => 'balanco',
                'quantidade' => $quantidade,
                'saldo_anterior' => $saldoAnteriorTotal,
                'saldo_posterior' => $produto->saldo,
                'origem' => $origem,
                'referencia' => ($tipoEstoque === 'fisico' ? '[FÍSICO] ' : '[VIRTUAL] ') . ($referencia ?? ''),
                'user_id' => $userId,
            ]);
        });

        if ($syncBling) {
            $obs = "Balanço {$tipoEstoque} (={$novoSaldo}) total={$produto->saldo}";
            if ($referencia) $obs .= " - {$referencia}";
            SyncEstoqueBlingJob::dispatch($produto->sku, $produto->saldo, $obs, 'B');
        }

        return ['success' => true, 'saldo' => $produto->saldo, 'saldo_fisico' => $produto->saldo_fisico, 'saldo_virtual' => $produto->saldo_virtual];
    }

    /**
     * Processa saída por venda (desmembra kits automaticamente).
     * Prioridade: desconta do físico. Se físico = 0, desconta do virtual.
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

            if ($produto->isKit()) {
                foreach ($produto->componentes as $comp) {
                    $qtdComp = $comp->pivot->quantidade * $qtd;
                    $tipoEstoque = $comp->saldo_fisico > 0 ? 'fisico' : 'virtual';
                    $res = self::saida($comp->sku, $qtdComp, $origem, $referencia, null, true, $tipoEstoque);
                    $resultados[] = array_merge($res, ['sku' => $comp->sku, 'kit_de' => $sku]);
                }
            } else {
                $tipoEstoque = $produto->saldo_fisico > 0 ? 'fisico' : 'virtual';
                $res = self::saida($sku, $qtd, $origem, $referencia, null, true, $tipoEstoque);
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
        bool $syncBling,
        string $tipoEstoque = 'virtual'
    ): array {
        $produto = ProdutoEstoque::where('sku', $sku)->where('ativo', true)->first();
        if (!$produto) {
            return ['success' => false, 'erro' => "SKU {$sku} não encontrado ou inativo"];
        }

        $campoSaldo = $tipoEstoque === 'fisico' ? 'saldo_fisico' : 'saldo_virtual';
        $saldoAnteriorTipo = $produto->{$campoSaldo};
        $saldoAnteriorTotal = $produto->saldo;

        $novoSaldoTipo = $tipo === 'entrada'
            ? $saldoAnteriorTipo + $quantidade
            : max(0, $saldoAnteriorTipo - $quantidade);

        DB::transaction(function () use ($produto, $tipo, $quantidade, $saldoAnteriorTotal, $novoSaldoTipo, $campoSaldo, $tipoEstoque, $origem, $referencia, $userId) {
            $produto->{$campoSaldo} = $novoSaldoTipo;
            $produto->recalcularSaldo();
            $produto->save();

            MovimentacaoEstoque::create([
                'produto_estoque_id' => $produto->id,
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'saldo_anterior' => $saldoAnteriorTotal,
                'saldo_posterior' => $produto->saldo,
                'origem' => $origem,
                'referencia' => ($tipoEstoque === 'fisico' ? '[FÍSICO] ' : '[VIRTUAL] ') . ($referencia ?? ''),
                'user_id' => $userId,
            ]);
        });

        if ($syncBling) {
            $obs = $tipo === 'entrada' ? "Entrada {$tipoEstoque} (+{$quantidade})" : "Saída {$tipoEstoque} (-{$quantidade})";
            if ($referencia) $obs .= " - {$referencia}";
            // Sempre envia o saldo TOTAL pro Bling como balanço
            SyncEstoqueBlingJob::dispatch($produto->sku, $produto->saldo, $obs, 'B');
        }

        Log::info("Estoque: {$tipo} {$tipoEstoque} SKU {$sku} qtd={$quantidade} | {$saldoAnteriorTotal} → {$produto->saldo} | {$origem}");

        return ['success' => true, 'saldo' => $produto->saldo, 'saldo_fisico' => $produto->saldo_fisico, 'saldo_virtual' => $produto->saldo_virtual];
    }
}
