<?php

namespace App\Services;

use App\Models\PedidoBlingStaging;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ShopeePlanilhaService
{
    /**
     * Processa planilha da Shopee e atualiza pedidos no staging.
     * Agrupa linhas por pedido (um pedido pode ter vários itens/linhas).
     */
    public static function processar(string $filePath): array
    {
        $resultado = [
            'processados' => 0,
            'nao_encontrados' => 0,
            'erros' => 0,
            'detalhes' => [],
        ];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Exception $e) {
            return ['erros' => 1, 'detalhes' => ["Erro ao ler arquivo: {$e->getMessage()}"]];
        }

        // Agrupar linhas por ID do pedido (coluna A)
        $pedidosAgrupados = [];
        $header = null;

        foreach ($rows as $row) {
            if (!$header) {
                $header = $row;
                continue;
            }

            $pedidoId = trim($row['A'] ?? '');
            if (empty($pedidoId)) {
                continue;
            }

            $pedidosAgrupados[$pedidoId][] = $row;
        }

        // Processar cada pedido agrupado
        foreach ($pedidosAgrupados as $pedidoId => $linhas) {
            $staging = PedidoBlingStaging::where('numero_loja', $pedidoId)
                ->where('status', 'pendente')
                ->first();

            if (!$staging) {
                $resultado['nao_encontrados']++;
                continue;
            }

            try {
                $dados = self::calcularPedido($linhas);

                $updateData = [
                    'total_produtos' => $dados['total_produtos'],
                    'total_pedido' => $dados['total_pedido'],
                    'frete' => $dados['frete'],
                    'comissao_calculada' => $dados['comissao'],
                    'subsidio_pix' => $dados['subsidio_pix'],
                    'planilha_shopee' => true,
                ];

                // Só atualizar itens se a planilha trouxe dados válidos (SKU ou descrição)
                if (!empty($dados['itens']) && !empty($dados['itens'][0]['descricao'])) {
                    $updateData['itens'] = $dados['itens'];
                }

                $staging->update($updateData);

                $resultado['processados']++;
            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = "Pedido {$pedidoId}: {$e->getMessage()}";
                Log::error("Shopee planilha erro", ['pedido' => $pedidoId, 'error' => $e->getMessage()]);
            }
        }

        return $resultado;
    }

    /**
     * Calcula valores consolidados de um pedido a partir de suas linhas.
     */
    private static function calcularPedido(array $linhas): array
    {
        $totalProdutos = 0;
        $frete = 0;
        $comissao = 0;
        $subsidio = 0;
        $totalGlobal = 0;
        $freteCalculado = false;
        $itens = [];

        foreach ($linhas as $row) {
            // Preço acordado (coluna R) × Quantidade (coluna S) = total do item
            $precoAcordado = self::parseDecimal($row['R'] ?? 0);
            $quantidade = self::parseDecimal($row['S'] ?? 1);
            $totalProdutos += $precoAcordado * $quantidade;

            // Montar item com dados reais da Shopee
            $itens[] = [
                'codigo' => trim($row['N'] ?? ''),       // Nº de referência do SKU principal (coluna N)
                'descricao' => trim($row['O'] ?? ''),     // Nome do Produto (coluna O)
                'quantidade' => (int) $quantidade,
                'valor' => round($precoAcordado, 2),
            ];

            // Taxa de comissão bruta (coluna AQ)
            $comissao += abs(self::parseDecimal($row['AQ'] ?? 0));

            // Taxa de serviço bruta (coluna AS)
            $comissao += abs(self::parseDecimal($row['AS'] ?? 0));

            // Cupom Shopee (coluna AE)
            $subsidio += abs(self::parseDecimal($row['AE'] ?? 0));

            // Total global (coluna AU)
            $totalGlobal += self::parseDecimal($row['AU'] ?? 0);

            // Frete: calcular apenas uma vez (é por pedido, não por item)
            if (!$freteCalculado) {
                $opcaoEnvio = trim($row['G'] ?? '');
                $taxaEnvioComprador = self::parseDecimal($row['AM'] ?? 0);
                $descontoFrete = self::parseDecimal($row['AN'] ?? 0);

                if (stripos($opcaoEnvio, 'vendedor') !== false
                    || stripos($opcaoEnvio, 'logística do vendedor') !== false) {
                    $frete = $taxaEnvioComprador + abs($descontoFrete);
                } elseif (stripos($opcaoEnvio, 'xpress') !== false) {
                    $frete = 0;
                } else {
                    $frete = $taxaEnvioComprador;
                }

                $freteCalculado = true;
            }
        }

        return [
            'total_produtos' => round($totalProdutos, 2),
            'total_pedido' => round(abs($totalGlobal), 2),
            'frete' => round($frete, 2),
            'comissao' => round($comissao, 2),
            'subsidio_pix' => round($subsidio, 2),
            'itens' => $itens,
        ];
    }

    /**
     * Converte valor da planilha para decimal
     */
    private static function parseDecimal($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = str_replace('.', '', (string) $value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value);

        return (float) $value;
    }
}
