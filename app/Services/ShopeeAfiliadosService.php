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
    /**
     * Colunas esperadas no cabeçalho para validação.
     * Índice (base 0) => substring que deve conter (case-insensitive).
     */
    public const COLUNAS_ESPERADAS = [
        0 => 'id do pedido',
        32 => 'despesas',
    ];

    /**
     * Valida se o cabeçalho da planilha corresponde ao mapeamento esperado.
     */
    public static function validarCabecalho(string $filePath): array
    {
        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return ['valido' => false, 'divergencias' => ['Erro ao abrir arquivo']];
            }

            $header = fgetcsv($handle);
            fclose($handle);

            if (!$header) {
                return ['valido' => false, 'divergencias' => ['Arquivo vazio ou sem cabeçalho']];
            }
        } catch (\Exception $e) {
            return ['valido' => false, 'divergencias' => ["Erro ao ler arquivo: {$e->getMessage()}"]];
        }

        $divergencias = [];
        foreach (self::COLUNAS_ESPERADAS as $idx => $substringEsperada) {
            $valorReal = mb_strtolower(trim($header[$idx] ?? ''));
            if (!str_contains($valorReal, $substringEsperada)) {
                $colLetra = self::idxParaLetra($idx);
                $divergencias[] = "Coluna {$colLetra} (pos {$idx}): esperado conter \"{$substringEsperada}\" → encontrado \"" . trim($header[$idx] ?? '(vazio)') . "\"";
            }
        }

        return [
            'valido' => empty($divergencias),
            'divergencias' => $divergencias,
        ];
    }

    private static function idxParaLetra(int $idx): string
    {
        $letra = '';
        $idx++;
        while ($idx > 0) {
            $idx--;
            $letra = chr(65 + ($idx % 26)) . $letra;
            $idx = intdiv($idx, 26);
        }
        return $letra;
    }

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

                // Gravar no campo separado (não soma mais no comissao)
                $venda->update([
                    'comissao_afiliado' => $despesa,
                    'planilha_afiliado_processada' => true,
                ]);

                // Recalcular margens
                VendaRecalculoService::recalcularMargens($venda);

                if ($despesa > 0) {
                    $resultado['atualizados']++;
                    $resultado['detalhes'][] = "{$pedidoId}: R$ " . number_format($despesa, 2, ',', '.');
                } else {
                    $resultado['sem_valor']++;
                }
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
