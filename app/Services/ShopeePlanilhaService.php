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
     *  - R = Preço acordado, S = Quantidade
     *  - AQ = Taxa de comissão, AS = Taxa de serviço
     *  - AE = Cupom Shopee (subsídio), Y = Ajuste ação comercial (subsídio pix)
     *  - AU = Total global do pedido
     *  - G = Opção de envio (Xpress → frete = 0)
     *  - AM = Taxa envio comprador, AN = Desconto frete
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

            // Ajuste por participação em ação comercial (coluna Y) = subsídio pix
            $subsidio += abs(self::parseDecimal($row['Y'] ?? 0));

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
