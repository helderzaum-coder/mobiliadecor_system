<?php

namespace App\Services;

use App\Models\PlanilhaMmDado;
use Illuminate\Support\Facades\Log;

class MadeiraMadeiraPlanilhaService
{
    public static function processar(string $filePath): array
    {
        $resultado = ['processados' => 0, 'nao_encontrados' => 0, 'erros' => 0];

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['processados' => 0, 'nao_encontrados' => 0, 'erros' => 1];
        }

        // Header
        $header = fgetcsv($handle, 0, ';', '"');
        if (!$header) {
            fclose($handle);
            return ['processados' => 0, 'nao_encontrados' => 0, 'erros' => 1];
        }

        // Limpar BOM e espaços
        $header = array_map(fn ($h) => trim(preg_replace('/[\x{FEFF}]/u', '', $h)), $header);

        $colMap = [];
        foreach ($header as $i => $col) {
            $colMap[mb_strtolower($col)] = $i;
        }

        $iPedido = $colMap['pedido'] ?? null;
        $iValorPedido = $colMap['valor pedido'] ?? null;
        $iComissao = $colMap['comisso'] ?? $colMap['comissao'] ?? $colMap['comissão'] ?? null;
        $iTipoPagamento = $colMap['tipo pagamento'] ?? null;
        $iValorOriginal = $colMap['valor original'] ?? null;
        $iDesconto = $colMap['% desconto'] ?? null;
        $iValor = $colMap['valor'] ?? null;

        if ($iPedido === null || $iComissao === null) {
            fclose($handle);
            Log::error('PlanilhaMM: colunas obrigatórias não encontradas', ['header' => $header]);
            return ['processados' => 0, 'nao_encontrados' => 0, 'erros' => 1];
        }

        while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
            try {
                $numeroPedido = trim($row[$iPedido] ?? '');
                if (empty($numeroPedido)) continue;

                $comissao = self::parseDecimal($row[$iComissao] ?? '0');
                $valorPedido = self::parseDecimal($row[$iValorPedido] ?? '0');
                $valorOriginal = self::parseDecimal($row[$iValorOriginal] ?? '0');
                $desconto = self::parseDecimal($row[$iDesconto] ?? '0');
                $valorComDesconto = self::parseDecimal($row[$iValor] ?? '0');
                $tipoPagamento = trim($row[$iTipoPagamento] ?? '');

                PlanilhaMmDado::updateOrCreate(
                    ['numero_pedido' => $numeroPedido],
                    [
                        'valor_original' => $valorOriginal,
                        'percentual_desconto' => $desconto,
                        'valor_com_desconto' => $valorComDesconto,
                        'comissao' => $comissao,
                        'valor_pedido' => $valorPedido,
                        'tipo_pagamento' => $tipoPagamento,
                        'dados_originais' => array_combine($header, $row),
                    ]
                );

                $resultado['processados']++;
            } catch (\Exception $e) {
                $resultado['erros']++;
                Log::warning("PlanilhaMM: erro na linha", ['error' => $e->getMessage()]);
            }
        }

        fclose($handle);
        return $resultado;
    }

    private static function parseDecimal(string $value): float
    {
        $value = trim($value);
        // "1.024,15" → 1024.15 | "165.47" → 165.47
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        return (float) $value;
    }
}
