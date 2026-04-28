<?php

namespace App\Services;

use App\Models\PlanilhaMmDado;
use Illuminate\Support\Facades\Log;

class MadeiraMadeiraPlanilhaService
{
    public static function processar(string $filePath): array
    {
        $resultado = ['processados' => 0, 'nao_encontrados' => 0, 'erros' => 0];

        // Ler arquivo e converter encoding se necessário
        $content = file_get_contents($filePath);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $lines = explode("\n", $content);
        if (empty($lines)) {
            return ['processados' => 0, 'nao_encontrados' => 0, 'erros' => 1];
        }

        // Header
        $headerLine = array_shift($lines);
        $header = str_getcsv($headerLine, ';', '"');
        $header = array_map(fn ($h) => trim(preg_replace('/[\x{FEFF}\x{00A0}]/u', '', $h)), $header);

        // Mapear colunas por nome normalizado (sem acentos)
        $colMap = [];
        foreach ($header as $i => $col) {
            $colMap[self::normalizar($col)] = $i;
        }

        $iPedido = $colMap['pedido'] ?? null;
        $iValorPedido = $colMap['valor pedido'] ?? null;
        $iComissao = $colMap['comissao'] ?? null;
        $iTipoPagamento = $colMap['tipo pagamento'] ?? null;
        $iValorOriginal = $colMap['valor original'] ?? null;
        $iDesconto = $colMap['% desconto'] ?? null;
        $iValor = $colMap['valor'] ?? null;

        if ($iPedido === null || $iComissao === null) {
            Log::error('PlanilhaMM: colunas obrigatórias não encontradas', [
                'header_normalizado' => array_keys($colMap),
            ]);
            return ['processados' => 0, 'nao_encontrados' => 0, 'erros' => 1];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $row = str_getcsv($line, ';', '"');

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
                        'dados_originais' => array_combine(
                            array_slice($header, 0, count($row)),
                            $row
                        ),
                    ]
                );

                $resultado['processados']++;
            } catch (\Exception $e) {
                $resultado['erros']++;
                Log::warning("PlanilhaMM: erro na linha", ['error' => $e->getMessage()]);
            }
        }

        return $resultado;
    }

    private static function normalizar(string $str): string
    {
        $str = mb_strtolower($str);
        // Remover acentos
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        return trim($str);
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
