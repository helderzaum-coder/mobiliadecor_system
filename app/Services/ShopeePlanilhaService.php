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
 * ║  - Frete (coluna AP/AQ, com lógica Xpress = 0)                    ║
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
     * Colunas esperadas no cabeçalho (coluna Excel => nome esperado).
     * Usado para validar se a planilha está no formato correto antes de processar.
     */
    public const COLUNAS_ESPERADAS = [
        'A'  => 'ID do pedido',
        'B'  => 'Tipo de pedido',
        'H'  => 'Opção de envio',
        'O'  => 'Nome do Produto',
        'P'  => 'Número de referência SKU',
        'S'  => 'Preço acordado',
        'T'  => 'Quantidade',
        'V'  => 'Subtotal do produto',
        'AI' => 'Ajuste por pagamento via PIX',
        'AP' => 'Taxa de envio pagas pelo comprador',
        'AQ' => 'Desconto de Frete Aproximado',
        'AU' => 'Taxa de comissão líquida',
        'AW' => 'Taxa de serviço líquida',
        'BA' => 'Nome do destinatário',
        'BC' => 'CPF do Comprador',
    ];

    /**
     * Valida se o cabeçalho da planilha corresponde ao mapeamento esperado.
     * Retorna array com 'valido' => bool e 'divergencias' => [...]
     */
    public static function validarCabecalho(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Exception $e) {
            return ['valido' => false, 'divergencias' => ["Erro ao ler arquivo: {$e->getMessage()}"]];
        }

        $header = null;
        foreach ($rows as $row) {
            $header = $row;
            break;
        }

        if (!$header) {
            return ['valido' => false, 'divergencias' => ['Planilha vazia ou sem cabeçalho']];
        }

        $divergencias = [];
        foreach (self::COLUNAS_ESPERADAS as $coluna => $nomeEsperado) {
            $valorReal = mb_strtolower(trim($header[$coluna] ?? ''));
            $esperado = mb_strtolower($nomeEsperado);

            if ($valorReal !== $esperado) {
                $divergencias[] = "Coluna {$coluna}: esperado \"{$nomeEsperado}\" → encontrado \"" . trim($header[$coluna] ?? '(vazio)') . "\"";
            }
        }

        return [
            'valido' => empty($divergencias),
            'divergencias' => $divergencias,
        ];
    }

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
     * ⚠️ MAPEAMENTO (planilha Shopee atualizada jul/2026):
     *  - S = Preço acordado, T = Quantidade
     *  - V = Subtotal do produto
     *  - AI = Ajuste por pagamento via PIX (subsídio pix)
     *  - H = Opção de envio (Xpress → frete = 0)
     *  - AP = Taxa envio comprador, AQ = Desconto de Frete Aproximado
     *  - AO = Valor Total (informativo, não usar no cálculo)
     *  - AU = Taxa de comissão líquida, AW = Taxa de serviço líquida
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
            // Subtotal do produto (coluna V)
            $precoProduto = self::parseDecimal($row['V'] ?? 0);
            $precosProduto += $precoProduto;

            // Subsídio Pix (coluna AI)
            $subsidioPix += abs(self::parseDecimal($row['AI'] ?? 0));

            // Quantidade (coluna T)
            $quantidade = (int) (self::parseDecimal($row['T'] ?? 1) ?: 1);

            // SKU (coluna P) e Descrição (coluna O)
            $skuRaw = trim($row['P'] ?? '');
            $descRaw = trim($row['O'] ?? '');
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

            // Taxa de comissão líquida (AU) + Taxa de serviço líquida (AW)
            // Shopee repete o valor TOTAL do pedido em cada linha — pegar apenas uma vez
            if (!$comissaoCalculada) {
                $comissao = abs(self::parseDecimal($row['AU'] ?? 0))
                          + abs(self::parseDecimal($row['AW'] ?? 0));
                $comissaoCalculada = true;
            }

            // Frete: calcular apenas uma vez (por pedido)
            if (!$freteCalculado) {
                // Verificar se é Shopee Xpress (coluna H)
                $opcaoEnvio = strtolower(trim($row['H'] ?? ''));
                $isXpress = str_contains($opcaoEnvio, 'xpress') || str_contains($opcaoEnvio, 'express');

                if ($isXpress) {
                    $frete = 0;
                } else {
                    // Frete = Taxa envio comprador (AP) + Desconto frete (AQ)
                    $taxaEnvioComprador = self::parseDecimal($row['AP'] ?? 0);
                    $descontoFrete = self::parseDecimal($row['AQ'] ?? 0);
                    $frete = $taxaEnvioComprador + abs($descontoFrete);
                }
                $freteCalculado = true;
            }
        }

        // Subtotal real = Preço produto - Subsídio Pix
        $subtotalReal = $precosProduto - $subsidioPix;

        // Total do pedido
        // Frete líquido = AP (taxa envio comprador) — AQ já está somado no frete acima
        // Total = subtotal + frete (o que o vendedor efetivamente recebe de produto + frete)
        $totalPedido = $subtotalReal + $frete;

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
