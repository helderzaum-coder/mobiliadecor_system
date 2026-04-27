<?php

namespace App\Services;

use App\Models\PedidoBlingStaging;
use App\Models\Venda;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WebcontinentalPlanilhaService
{
    /**
     * Processa planilha de pedidos da Webcontinental.
     *
     * Colunas relevantes:
     * D = Pedido Parceiro (chave para vincular)
     * F = Pedido ERP (numero_loja no Bling)
     * J = Total do Pedido
     * K = Valor do Frete
     * L = Valor dos Produtos
     * M = Desconto Total do Pedido
     * N = Valor Repasse
     * O = Valor de Comissão Retido
     */
    public static function processar(string $filePath): array
    {
        $resultado = [
            'processados' => 0,
            'nao_encontrados' => 0,
            'ja_processados' => 0,
            'com_divergencia' => 0,
            'erros' => 0,
            'detalhes' => [],
        ];

        try {
            $readerType = IOFactory::identify($filePath);
            $reader = IOFactory::createReader($readerType);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Exception $e) {
            return ['processados' => 0, 'nao_encontrados' => 0, 'ja_processados' => 0, 'erros' => 1, 'detalhes' => ["Erro ao ler arquivo: {$e->getMessage()}"]];
        }

        $header = null;
        $pedidos = [];

        foreach ($rows as $row) {
            if (!$header) {
                $header = $row;
                continue;
            }

            $pedidoErp = trim($row['F'] ?? '');
            if (empty($pedidoErp) || isset($pedidos[$pedidoErp])) {
                continue;
            }

            $pedidos[$pedidoErp] = $row;
        }

        Log::info('WebcontinentalPlanilha: iniciando', [
            'total_pedidos_planilha' => count($pedidos),
        ]);

        // Buscar no staging pelo numero_loja (coluna F = Pedido ERP)
        $stagings = PedidoBlingStaging::whereIn('numero_loja', array_keys($pedidos))
            ->whereNotNull('bling_id')
            ->get()
            ->keyBy('numero_loja');

        foreach ($pedidos as $pedidoErp => $row) {
            $staging = $stagings[$pedidoErp] ?? null;

            if (!$staging) {
                $resultado['nao_encontrados']++;
                continue;
            }

            if ($staging->planilha_shopee) {
                $resultado['ja_processados']++;
                continue;
            }

            try {
                $totalPedido = self::parseDecimal($row['J'] ?? 0);
                $frete = self::parseDecimal($row['K'] ?? 0);
                $totalProdutos = self::parseDecimal($row['L'] ?? 0);
                $desconto = abs(self::parseDecimal($row['M'] ?? 0));
                $valorRepasse = self::parseDecimal($row['N'] ?? 0);
                $comissaoRetida = self::parseDecimal($row['O'] ?? 0);

                // Validar comissão: (Produtos + Frete + Desconto) * 22%
                $baseComissao = $totalProdutos + $frete + $desconto;
                $comissaoEsperada = round($baseComissao * 0.22, 2);
                $diferenca = round($comissaoEsperada - $comissaoRetida, 2);

                $obsValidacao = '';
                if (abs($diferenca) > 0.10) {
                    $obsValidacao = $diferenca > 0
                        ? "Webcontinental absorveu R$ {$diferenca} do desconto na comissão"
                        : "Comissão R$ " . abs($diferenca) . " acima do esperado";
                    $resultado['com_divergencia']++;
                    $resultado['detalhes'][] = "{$pedidoErp}: {$obsValidacao} (esperado R$ {$comissaoEsperada}, retido R$ {$comissaoRetida})";
                    Log::info("WebcontinentalPlanilha: {$pedidoErp} - {$obsValidacao}", [
                        'base' => $baseComissao,
                        'esperada' => $comissaoEsperada,
                        'retida' => $comissaoRetida,
                        'desconto' => $desconto,
                    ]);
                }

                $staging->update([
                    'total_produtos' => $totalProdutos,
                    'total_pedido' => $totalPedido,
                    'frete' => $frete,
                    'comissao_calculada' => $comissaoRetida,
                    'planilha_shopee' => true,
                    'observacoes' => trim(($staging->observacoes ?? '') . ($obsValidacao ? "\n{$obsValidacao}" : '')),
                ]);

                // Atualizar venda se já aprovada
                Venda::where('bling_id', $staging->bling_id)->update([
                    'total_produtos' => $totalProdutos,
                    'valor_total_venda' => $totalPedido,
                    'valor_frete_cliente' => $frete,
                    'comissao' => $comissaoRetida,
                    'planilha_processada' => true,
                ]);

                $resultado['processados']++;

            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = "{$pedidoErp}: {$e->getMessage()}";
            }
        }

        Log::info('WebcontinentalPlanilha: concluído', $resultado);

        return $resultado;
    }

    private static function parseDecimal($value): float
    {
        if (is_numeric($value)) return (float) $value;
        $str = trim((string) $value);
        if ($str === '') return 0;
        if (str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        }
        return is_numeric($str) ? (float) $str : 0;
    }
}
