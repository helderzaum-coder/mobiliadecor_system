<?php

namespace App\Services;

use App\Models\PedidoBlingStaging;
use App\Models\PlanilhaShopeeDado;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  ATENÇÃO: CÓDIGO ESTÁVEL E FUNCIONAL — NÃO SOBRESCREVER           ║
 * ║                                                                    ║
 * ║  Este serviço processa a planilha da Shopee e atualiza APENAS      ║
 * ║  dados financeiros no staging:                                     ║
 * ║  - Comissão (colunas AT + AV)                                      ║
 * ║  - Subsídio Pix (colunas AE + Y)                                   ║
 * ║  - Frete (coluna AO/AP, com lógica Xpress = 0)                    ║
 * ║  - Total produtos (coluna R × S)                                   ║
 * ║  - Total pedido (coluna AU)                                        ║
 * ║                                                                    ║
 * ║  IMPORTANTE: A planilha NÃO sobrescreve itens do Bling.            ║
 * ║  Os itens (SKU, descrição, custo) vêm da importação Bling.         ║
 * ║                                                                    ║
 * ║  Referência funcional: commit de 23/03/2026                        ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */
class ShopeePlanilhaService
{
    /**
     * Processa planilha da Shopee e atualiza pedidos no staging.
     * Agrupa linhas por pedido (um pedido pode ter vários itens/linhas).
     *
     * ⚠️ NÃO ALTERAR: Atualiza SOMENTE dados financeiros no staging.
     * Nunca sobrescrever itens (SKU, descrição, custo) — esses vêm do Bling.
     */
    public static function processar(string $filePath): array
    {
        Log::info("Shopee Planilha: Iniciando processamento", ['arquivo' => basename($filePath)]);

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
            Log::error("Shopee Planilha: Erro ao ler arquivo", ['error' => $e->getMessage()]);
            return ['processados' => 0, 'nao_encontrados' => 0, 'erros' => 1, 'detalhes' => ["Erro ao ler arquivo: {$e->getMessage()}"]];
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

        Log::info("Shopee Planilha: " . count($pedidosAgrupados) . " pedidos encontrados na planilha");

        // Pré-carregar pedidos pendentes do staging
        $stagingMap = PedidoBlingStaging::where('status', 'pendente')
            ->whereNotNull('numero_loja')
            ->pluck('id', 'numero_loja')
            ->toArray();

        // Processar cada pedido agrupado
        foreach ($pedidosAgrupados as $pedidoId => $linhas) {
            try {
                $dados = self::calcularPedido($linhas);

                // Salvar/atualizar no banco para reprocessamento futuro
                PlanilhaShopeeDado::updateOrCreate(
                    ['numero_pedido' => $pedidoId],
                    [
                        'taxa_comissao' => $dados['comissao'],
                        'taxa_servico' => 0,
                        'taxa_envio' => $dados['frete'],
                        'total_taxas' => $dados['comissao'],
                        'dados_originais' => $dados,
                    ]
                );

                // Aplicar no staging se existir (usando mapa pré-carregado)
                $stagingId = $stagingMap[$pedidoId] ?? null;
                if (!$stagingId) {
                    $resultado['nao_encontrados']++;
                    continue;
                }

                $updateData = [
                    'total_produtos' => $dados['total_produtos'],
                    'total_pedido' => $dados['total_pedido'],
                    'frete' => $dados['frete'],
                    'comissao_calculada' => $dados['comissao'],
                    'subsidio_pix' => $dados['subsidio_pix'],
                    'planilha_shopee' => true,
                ];

                // Se frete = 0 (Xpress), zerar custo_frete também
                if ((float) $dados['frete'] == 0) {
                    $updateData['custo_frete'] = 0;
                }

                PedidoBlingStaging::where('id', $stagingId)->update($updateData);
                $resultado['processados']++;
            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = "Pedido {$pedidoId}: {$e->getMessage()}";
                Log::error("Shopee planilha erro", ['pedido' => $pedidoId, 'error' => $e->getMessage()]);
            }
        }

        Log::info("Shopee Planilha: Concluído", $resultado);

        return $resultado;
    }

    /**
     * Calcula valores consolidados de um pedido a partir de suas linhas.
     *
     * ⚠️ MAPEAMENTO ATUALIZADO (planilha Shopee SEM coluna "Tipo de pedido"):
     *  - R = Preço acordado, S = Quantidade
     *  - U = Subtotal do produto
     *  - AH = Ajuste por pagamento via PIX (ps_csv_pix_discount_br)
     *  - G = Opção de envio (Xpress → frete = 0)
     *  - AO = Taxa envio comprador, AP = Desconto de Frete Aproximado
     *  - AT = Taxa de comissão líquida, AV = Taxa de serviço líquida
     */
    private static function calcularPedido(array $linhas): array
    {
        $precosProduto = 0;
        $frete = 0;
        $comissao = 0;
        $subsidioPix = 0;
        $freteCalculado = false;
        $comissaoCalculada = false;
        $itens = [];

        foreach ($linhas as $row) {
            // Subtotal do produto (coluna U)
            $precoProduto = self::parseDecimal($row['U'] ?? 0);
            $precosProduto += $precoProduto;

            // Subsídio Pix (coluna AH = ps_csv_pix_discount_br)
            $subsidioPix += abs(self::parseDecimal($row['AH'] ?? 0));

            // Quantidade (coluna S)
            $quantidade = (int) (self::parseDecimal($row['S'] ?? 1) ?: 1);

            // SKU (coluna O) e Descrição (coluna N)
            $skuRaw = trim($row['O'] ?? '');
            $descRaw = trim($row['N'] ?? '');
            if (preg_match('/^\d+$/', $skuRaw)) {
                $sku = $skuRaw;
                $desc = $descRaw;
            } elseif (preg_match('/^\d+$/', $descRaw)) {
                $sku = $descRaw;
                $desc = $skuRaw;
            } else {
                $sku = $skuRaw;
                $desc = $descRaw;
            }
            $itens[] = [
                'codigo' => $sku,
                'descricao' => $desc,
                'quantidade' => $quantidade,
                'valor' => round($precoProduto, 2),
            ];

            // Taxa de comissão líquida (AT) + Taxa de serviço líquida (AV)
            // Shopee repete o valor TOTAL do pedido em cada linha — pegar apenas uma vez
            if (!$comissaoCalculada) {
                $comissao = abs(self::parseDecimal($row['AT'] ?? 0))
                          + abs(self::parseDecimal($row['AV'] ?? 0));
                $comissaoCalculada = true;
            }

            // Frete: calcular apenas uma vez (por pedido)
            if (!$freteCalculado) {
                // Verificar se é Shopee Xpress (coluna G)
                $opcaoEnvio = strtolower(trim($row['G'] ?? ''));
                $isXpress = str_contains($opcaoEnvio, 'xpress') || str_contains($opcaoEnvio, 'express');

                if ($isXpress) {
                    $frete = 0;
                } else {
                    // Frete = Taxa envio comprador (AO) + Desconto de Frete Aproximado (AP)
                    $taxaEnvioComprador = self::parseDecimal($row['AO'] ?? 0);
                    $descontoFrete = self::parseDecimal($row['AP'] ?? 0);
                    $frete = $taxaEnvioComprador + abs($descontoFrete);
                }
                $freteCalculado = true;
            }
        }

        // Subtotal real = Preço produto - Subsídio Pix
        $subtotalReal = $precosProduto - $subsidioPix;

        // Total do pedido
        if ($frete == 0) {
            $totalPedido = $subtotalReal;
        } else {
            $totalPedido = $subtotalReal + $frete - abs(self::parseDecimal($linhas[0]['AP'] ?? 0));
        }

        return [
            'total_produtos' => round($subtotalReal, 2),
            'total_pedido' => round($totalPedido, 2),
            'frete' => round($frete, 2),
            'comissao' => round($comissao, 2),
            'subsidio_pix' => round($subsidioPix, 2),
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

    /**
     * Tenta aplicar dados de planilha já armazenados a um pedido do staging.
     * Chamado automaticamente quando um pedido novo é importado.
     * Retorna true se encontrou e aplicou dados.
     *
     * ⚠️ NÃO ALTERAR: Atualiza SOMENTE dados financeiros — nunca itens.
     */
    public static function reprocessarPedido(PedidoBlingStaging $staging): bool
    {
        if (!$staging->numero_loja) return false;

        $dado = PlanilhaShopeeDado::where('numero_pedido', $staging->numero_loja)->first();

        if (!$dado || !$dado->dados_originais) return false;

        $dados = $dado->dados_originais;

        $updateData = [
            'total_produtos' => $dados['total_produtos'] ?? $staging->total_produtos,
            'total_pedido' => $dados['total_pedido'] ?? $staging->total_pedido,
            'frete' => $dados['frete'] ?? $staging->frete,
            'comissao_calculada' => $dados['comissao'] ?? $staging->comissao_calculada,
            'subsidio_pix' => $dados['subsidio_pix'] ?? $staging->subsidio_pix,
            'planilha_shopee' => true,
        ];

        // Se frete = 0 (Xpress), zerar custo_frete também
        if ((float) ($dados['frete'] ?? 0) == 0) {
            $updateData['custo_frete'] = 0;
        }

        $staging->update($updateData);

        Log::info("Shopee planilha reprocessada automaticamente para pedido {$staging->numero_loja}");
        return true;
    }
}
