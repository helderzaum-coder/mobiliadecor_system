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
     * Nomes de colunas requeridas (chave interna => nome exato no cabeçalho).
     * O mapeamento é feito dinamicamente por nome, não por posição.
     */
    public const COLUNAS_REQUERIDAS = [
        'id_pedido'        => 'ID do pedido',
        'opcao_envio'      => 'Opção de envio',
        'nome_produto'     => 'Nome do Produto',
        'sku'              => 'Número de referência SKU',
        'quantidade'       => 'Quantidade',
        'subtotal_produto' => 'Subtotal do produto',
        'cupom_vendedor'   => 'Cupom do vendedor',
        'cupom_shopee_col' => 'Cupom',
        'ajuste_pix'       => 'Ajuste por pagamento via PIX',
        'taxa_envio'       => 'Taxa de envio pagas pelo comprador',
        'desconto_frete'   => 'Desconto de Frete Aproximado',
        'comissao_liquida' => 'Taxa de comissão líquida',
        'servico_liquido'  => 'Taxa de serviço líquida',
        'nome_destinatario'=> 'Nome do destinatário',
        'cpf_comprador'    => 'CPF do Comprador',
    ];

    /**
     * Constrói mapa chave => coluna_excel a partir do cabeçalho real.
     */
    public static function mapearColunas(array $header): array
    {
        $mapa = [];
        foreach ($header as $col => $valor) {
            $normalizado = mb_strtolower(trim((string) $valor));
            foreach (self::COLUNAS_REQUERIDAS as $chave => $nomeEsperado) {
                if ($normalizado === mb_strtolower($nomeEsperado)) {
                    $mapa[$chave] = $col;
                }
            }
        }
        return $mapa;
    }

    /**
     * Valida se o cabeçalho contém todas as colunas requeridas (por nome).
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

        $mapa = self::mapearColunas($header);
        $divergencias = [];

        foreach (self::COLUNAS_REQUERIDAS as $chave => $nomeEsperado) {
            if (!isset($mapa[$chave])) {
                $divergencias[] = "Coluna não encontrada: \"{$nomeEsperado}\"";
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

        // Agrupar linhas por ID do pedido (busca dinâmica por nome de coluna)
        $pedidosAgrupados = [];
        $header = null;
        $mapa = [];

        foreach ($rows as $row) {
            if (!$header) {
                $header = $row;
                $mapa = self::mapearColunas($header);
                continue;
            }

            $colId = $mapa['id_pedido'] ?? 'A';
            $pedidoId = trim($row[$colId] ?? '');
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
                $dados = self::calcularPedido($linhas, $mapa);

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
     * Usa mapa dinâmico de colunas (por nome) para ser resiliente a mudanças de layout.
     */
    private static function calcularPedido(array $linhas, array $mapa): array
    {
        $precosProduto = 0;
        $frete = 0;
        $comissao = 0;
        $subsidioPix = 0;
        $cupomVendedor = 0;
        $cupomShopee = 0;
        $freteCalculado = false;
        $comissaoCalculada = false;
        $itens = [];

        $val = fn(string $k, array $r) => $r[$mapa[$k] ?? ''] ?? 0;
        $str = fn(string $k, array $r) => trim((string) ($r[$mapa[$k] ?? ''] ?? ''));

        foreach ($linhas as $row) {
            $precoProduto = self::parseDecimal($val('subtotal_produto', $row));
            $precosProduto += $precoProduto;

            $subsidioPix += abs(self::parseDecimal($val('ajuste_pix', $row)));

            if ($cupomVendedor == 0) {
                $cupomVendedor = abs(self::parseDecimal($val('cupom_vendedor', $row)));
            }

            if ($cupomShopee == 0) {
                $cupomShopee = abs(self::parseDecimal($val('cupom_shopee_col', $row)));
            }

            $quantidade = (int) (self::parseDecimal($val('quantidade', $row)) ?: 1);

            $skuRaw  = $str('sku', $row);
            $descRaw = $str('nome_produto', $row);
            if (preg_match('/^\d+$/', $skuRaw)) {
                $sku = $skuRaw; $desc = $descRaw;
            } elseif (preg_match('/^\d+$/', $descRaw)) {
                $sku = $descRaw; $desc = $skuRaw;
            } else {
                $sku = $skuRaw; $desc = $descRaw;
            }
            $itens[] = ['codigo' => $sku, 'descricao' => $desc, 'quantidade' => $quantidade, 'valor' => round($precoProduto, 2)];

            if (!$comissaoCalculada) {
                $comissao = abs(self::parseDecimal($val('comissao_liquida', $row)))
                          + abs(self::parseDecimal($val('servico_liquido', $row)));
                $comissaoCalculada = true;
            }

            if (!$freteCalculado) {
                $opcaoEnvio = strtolower($str('opcao_envio', $row));
                $isXpress = str_contains($opcaoEnvio, 'xpress') || str_contains($opcaoEnvio, 'express');

                if ($isXpress) {
                    $frete = 0;
                } else {
                    $frete = self::parseDecimal($val('taxa_envio', $row))
                           + abs(self::parseDecimal($val('desconto_frete', $row)));
                }
                $freteCalculado = true;
            }
        }

        // total_produtos = V - cupom Shopee (Z) - subsidio_pix (AI)
        // Cupom Shopee reduz o subtotal que o vendedor recebe
        // Cupom vendedor (AE) fica separado — sai da margem mas nao do subtotal
        $totalPedido = ($precosProduto - $cupomShopee - $subsidioPix) + $frete;

        return [
            'total_produtos' => round($precosProduto - $cupomShopee - $subsidioPix, 2),
            'total_pedido'   => round($totalPedido, 2),
            'frete' => round($frete, 2),
            'comissao' => round($comissao, 2),
            'subsidio_pix' => round($subsidioPix, 2),
            'cupom_vendedor' => round($cupomVendedor, 2),
            'cupom_shopee' => round($cupomShopee, 2),
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
