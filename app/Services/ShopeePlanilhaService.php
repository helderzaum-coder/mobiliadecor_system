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
 * ║  - Comissão (colunas AQ + AS)                                      ║
 * ║  - Subsídio Pix (colunas AE + Y)                                   ║
 * ║  - Frete (coluna AM/AN, com lógica Xpress = 0)                    ║
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
                        'total_taxas' => $dados['comissao'] + $dados['subsidio_pix'],
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
     * ⚠️ NÃO ALTERAR COLUNAS SEM VERIFICAR PLANILHA REAL:
     *  - R = Preço acordado (já inclui subsídio pix), S = Quantidade
     *  - AQ = Taxa de comissão bruta, AS = Taxa de serviço bruta
     *  - AE = Cupom Shopee (subsídio marketplace)
     *  - Y = Ajuste por pagamento via PIX (subsídio pix) — embutido no preço R
     *        e também embutido nas taxas AQ+AS. Deve ser subtraído de ambos.
     *  - AU = Total global do pedido (renda líquida)
     *  - G = Opção de envio (Xpress → frete = 0)
     *  - AM = Taxa envio comprador, AN = Desconto frete
     *
     * Lógica de exibição (conforme tela Shopee):
     *  - Subtotal Produtos = (R × S) - Y  (preço real sem subsídio pix)
     *  - Subsídio Pix = Y + AE  (exibido separadamente)
     *  - Comissão = (AQ + AS) - Y  (comissão líquida, como aparece na Shopee)
     *  - Repasse = Subtotal + Frete - Comissão = Renda do pedido
     */
    private static function calcularPedido(array $linhas): array
    {
        $totalProdutos = 0;
        $frete = 0;
        $comissaoBruta = 0;
        $subsidioPix = 0;
        $cupomShopee = 0;
        $totalGlobal = 0;
        $freteCalculado = false;
        $itens = [];

        foreach ($linhas as $row) {
            // Preço acordado (coluna R) × Quantidade (coluna S) = total do item
            // ATENÇÃO: este valor já inclui o subsídio pix (coluna Y)
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
            $comissaoBruta += abs(self::parseDecimal($row['AQ'] ?? 0));

            // Taxa de serviço bruta (coluna AS)
            $comissaoBruta += abs(self::parseDecimal($row['AS'] ?? 0));

            // Cupom Shopee (coluna AE) — subsídio do marketplace
            $cupomShopee += abs(self::parseDecimal($row['AE'] ?? 0));

            // Ajuste por pagamento via PIX (coluna Y) — subsídio pix
            // Este valor está embutido no preço acordado (R) e nas taxas (AQ+AS)
            $subsidioPix += abs(self::parseDecimal($row['Y'] ?? 0));

            // Total global (coluna AU) — renda líquida do pedido
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

        // Subtotal real = preço acordado - subsídio pix (pix está embutido no preço R)
        $subtotalReal = $totalProdutos - $subsidioPix;

        // Comissão líquida = bruta - subsídio pix (pix está embutido nas taxas AQ+AS)
        $comissaoLiquida = $comissaoBruta - $subsidioPix;

        // Subsídio total = pix + cupom Shopee (exibido separadamente)
        $subsidioTotal = $subsidioPix + $cupomShopee;

        return [
            'total_produtos' => round($subtotalReal, 2),
            'total_pedido' => round($subtotalReal + $frete, 2),
            'frete' => round($frete, 2),
            'comissao' => round($comissaoLiquida, 2),
            'subsidio_pix' => round($subsidioTotal, 2),
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

        $staging->update($updateData);

        Log::info("Shopee planilha reprocessada automaticamente para pedido {$staging->numero_loja}");
        return true;
    }
}
