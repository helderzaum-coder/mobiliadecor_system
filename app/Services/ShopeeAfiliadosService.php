<?php

namespace App\Services;

use App\Models\Venda;
use Illuminate\Support\Facades\Log;

/**
 * Processa planilha de comissão de afiliados da Shopee (SellerConversionReport).
 * Soma o valor de "Despesas(R$)" na comissão da venda.
 */
class ShopeeAfiliadosService
{
    /**
     * Colunas requeridas — mapeamento por nome (igual ao ShopeePlanilhaService).
     */
    public const COLUNAS_REQUERIDAS = [
        'id_pedido' => ['ID do Pedido', 'Order id', 'Order ID'],
        'despesas'  => ['Despesas(R$)', 'Expense(R$)'],
    ];

    /**
     * Constrói mapa chave => índice a partir do cabeçalho real.
     */
    public static function mapearColunas(array $header): array
    {
        $mapa = [];
        foreach ($header as $idx => $valor) {
            $normalizado = mb_strtolower(trim((string) $valor));
            foreach (self::COLUNAS_REQUERIDAS as $chave => $variantes) {
                foreach ((array) $variantes as $nomeEsperado) {
                    if ($normalizado === mb_strtolower($nomeEsperado)) {
                        $mapa[$chave] = $idx;
                        break;
                    }
                }
            }
        }
        return $mapa;
    }

    /**
     * Retorna o primeiro nome da lista para exibição em erros.
     */
    private static function nomesParaExibir(array|string $variantes): string
    {
        return implode('" ou "', (array) $variantes);
    }

    /**
     * Valida se o cabeçalho contém todas as colunas requeridas (por nome).
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

        $mapa = self::mapearColunas($header);
        $divergencias = [];

        foreach (self::COLUNAS_REQUERIDAS as $chave => $variantes) {
            if (!isset($mapa[$chave])) {
                $divergencias[] = "Coluna não encontrada: \"" . self::nomesParaExibir($variantes) . "\"";
            }
        }

        return [
            'valido' => empty($divergencias),
            'divergencias' => $divergencias,
        ];
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

            $mapa = self::mapearColunas($header);
            $idxPedido   = $mapa['id_pedido'] ?? null;
            $idxDespesas = $mapa['despesas'] ?? null;

            if ($idxPedido === null || $idxDespesas === null) {
                fclose($handle);
                return ['atualizados' => 0, 'nao_encontrados' => 0, 'sem_valor' => 0, 'erros' => 1,
                    'detalhes' => ['Colunas obrigatórias não encontradas no cabeçalho.']];
            }

            Log::info("Shopee Afiliados: coluna id_pedido idx={$idxPedido}, despesas idx={$idxDespesas}");

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
