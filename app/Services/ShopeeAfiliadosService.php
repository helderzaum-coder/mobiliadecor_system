<?php

namespace App\Services;

use App\Models\Venda;
use Illuminate\Support\Facades\Log;

/**
 * Processa planilha de comissão de afiliados da Shopee (SellerConversionReport).
 * Soma o valor de "Despesas(R$)" na comissão da venda.
 *
 * Colunas relevantes:
 * A = ID do Pedido
 * AG (posição 33) = Despesas(R$) — valor da comissão de afiliados
 */
class ShopeeAfiliadosService
{
    public static function processar(string $filePath): array
    {
        $resultado = ['atualizados' => 0, 'nao_encontrados' => 0, 'sem_valor' => 0, 'erros' => 0, 'detalhes' => []];

        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return ['atualizados' => 0, 'nao_encontrados' => 0, 'sem_valor' => 0, 'erros' => 1, 'detalhes' => ['Erro ao abrir arquivo']];
            }

            $header = fgetcsv($handle);
            if (!$header) {
                fclose($handle);
                return ['atualizados' => 0, 'nao_encontrados' => 0, 'sem_valor' => 0, 'erros' => 1, 'detalhes' => ['Arquivo vazio']];
            }

            // Encontrar índice da coluna de despesas e pedido
            $idxPedido = 0; // Coluna A
            $idxDespesas = null;

            foreach ($header as $i => $col) {
                $colLimpa = mb_strtolower(trim($col));
                if (str_contains($colLimpa, 'despesas') || str_contains($colLimpa, 'expenses')) {
                    $idxDespesas = $i;
                    break;
                }
            }

            if ($idxDespesas === null) {
                // Fallback: posição 32 (AG = índice 32 em base 0)
                $idxDespesas = 32;
            }

            Log::info("Shopee Afiliados: coluna despesas idx={$idxDespesas}");

            // Agrupar por pedido (pode ter múltiplas linhas por pedido)
            $pedidosDespesas = [];

            while (($row = fgetcsv($handle)) !== false) {
                $pedidoId = trim($row[$idxPedido] ?? '');
                if (empty($pedidoId)) continue;

                $despesa = abs(self::parseDecimal($row[$idxDespesas] ?? '0'));
                if ($despesa <= 0) continue;

                if (!isset($pedidosDespesas[$pedidoId])) {
                    $pedidosDespesas[$pedidoId] = 0;
                }
                $pedidosDespesas[$pedidoId] += $despesa;
            }

            fclose($handle);

            Log::info("Shopee Afiliados: " . count($pedidosDespesas) . " pedidos com despesas");

            // Atualizar vendas
            foreach ($pedidosDespesas as $pedidoId => $despesa) {
                $despesa = round($despesa, 2);

                $venda = Venda::where('numero_pedido_canal', $pedidoId)->first();
                if (!$venda) {
                    $resultado['nao_encontrados']++;
                    continue;
                }

                // Somar despesa de afiliados na comissão
                $novaComissao = round((float) $venda->comissao + $despesa, 2);
                $venda->update(['comissao' => $novaComissao]);

                // Recalcular margens
                VendaRecalculoService::recalcularMargens($venda);

                $resultado['atualizados']++;
                $resultado['detalhes'][] = "{$pedidoId}: +R$ " . number_format($despesa, 2, ',', '.');
            }

        } catch (\Exception $e) {
            $resultado['erros']++;
            $resultado['detalhes'][] = "Erro: {$e->getMessage()}";
            Log::error("Shopee Afiliados erro", ['error' => $e->getMessage()]);
        }

        Log::info("Shopee Afiliados: Concluído", $resultado);
        return $resultado;
    }

    private static function parseDecimal($value): float
    {
        if (is_numeric($value)) return (float) $value;
        $value = str_replace('.', '', (string) $value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        return (float) $value;
    }
}
