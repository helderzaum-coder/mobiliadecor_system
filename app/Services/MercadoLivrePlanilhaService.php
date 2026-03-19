<?php

namespace App\Services;

use App\Models\PedidoBlingStaging;
use App\Models\PlanilhaMlDado;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MercadoLivrePlanilhaService
{
    /**
     * Processa planilha de vendas do Mercado Livre e atualiza rebate nos pedidos do staging.
     *
     * Colunas relevantes:
     * A = N.º de venda (chave)
     * H = Receita por produtos (BRL)
     * K = Tarifa de venda e impostos (BRL) (valor negativo)
     * L = Receita por envio (BRL)
     * M = Tarifas de envio (BRL) (valor negativo)
     * Q = Total (BRL)
     *
     * Fórmula rebate: Q - H - K - L - M
     * Se resultado > 0, existe rebate (desconto/bônus do ML).
     */
    public static function processar(string $filePath, string $blingAccount = 'primary'): array
    {
        $resultado = [
            'processados' => 0,
            'nao_encontrados' => 0,
            'sem_rebate' => 0,
            'erros' => 0,
            'detalhes' => [],
        ];

        try {
            $readerType = IOFactory::identify($filePath);
            $reader = IOFactory::createReader($readerType);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return ['processados' => 0, 'nao_encontrados' => 0, 'sem_rebate' => 0, 'erros' => 1, 'detalhes' => ["Erro ao ler arquivo: {$e->getMessage()}"]];
        }

        $highestRow = $sheet->getHighestRow();

        // Detectar linha do cabeçalho real (contém "N.º de venda" ou "N.o de venda" na coluna A)
        $linhaInicio = null;
        for ($i = 1; $i <= min(20, $highestRow); $i++) {
            $valA = (string) $sheet->getCell("A{$i}")->getValue();
            if (stripos($valA, 'venda') !== false && stripos($valA, 'N') !== false) {
                $linhaInicio = $i + 1; // dados começam na linha seguinte ao cabeçalho
                break;
            }
        }

        if (!$linhaInicio) {
            return ['processados' => 0, 'nao_encontrados' => 0, 'sem_rebate' => 0, 'erros' => 1, 'detalhes' => ['Cabeçalho não encontrado. Verifique se é uma planilha de vendas do ML.']];
        }

        for ($i = $linhaInicio; $i <= $highestRow; $i++) {
            $numeroPedido = trim((string) $sheet->getCell("A{$i}")->getValue());
            if (empty($numeroPedido)) continue;

            try {
                $receitaProdutos = self::parseDecimal($sheet->getCell("H{$i}")->getValue());
                $tarifaVenda     = self::parseDecimal($sheet->getCell("K{$i}")->getValue());
                $receitaEnvio    = self::parseDecimal($sheet->getCell("L{$i}")->getValue());
                $tarifasEnvio    = self::parseDecimal($sheet->getCell("M{$i}")->getValue());
                $total           = self::parseDecimal($sheet->getCell("Q{$i}")->getValue());

                $rebate = round($total - $receitaProdutos - $tarifaVenda - $receitaEnvio - $tarifasEnvio, 2);
                $temRebate = $rebate > 0.01;

                // Salvar/atualizar no banco para reprocessamento futuro
                PlanilhaMlDado::updateOrCreate(
                    ['numero_venda' => $numeroPedido, 'bling_account' => $blingAccount],
                    [
                        'receita_produtos' => $receitaProdutos,
                        'tarifa_venda' => $tarifaVenda,
                        'receita_envio' => $receitaEnvio,
                        'tarifas_envio' => $tarifasEnvio,
                        'total' => $total,
                        'rebate' => $temRebate ? $rebate : 0,
                        'tem_rebate' => $temRebate,
                    ]
                );

                // Aplicar no staging se existir
                $staging = PedidoBlingStaging::where('numero_loja', $numeroPedido)
                    ->where('bling_account', $blingAccount)
                    ->where('status', 'pendente')
                    ->first();

                if (!$staging) {
                    $resultado['nao_encontrados']++;
                    continue;
                }

                if ($temRebate) {
                    $staging->update(['ml_tem_rebate' => true, 'ml_valor_rebate' => $rebate]);
                    $resultado['processados']++;
                } else {
                    $staging->update(['ml_tem_rebate' => false, 'ml_valor_rebate' => 0]);
                    $resultado['sem_rebate']++;
                }
            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = "Pedido {$numeroPedido}: {$e->getMessage()}";
                Log::error("ML planilha erro", ['pedido' => $numeroPedido, 'error' => $e->getMessage()]);
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $sheet);

        return $resultado;
    }

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
     */
    public static function reprocessarPedido(PedidoBlingStaging $staging): bool
    {
        if (!$staging->numero_loja) return false;

        $dado = PlanilhaMlDado::where('numero_venda', $staging->numero_loja)
            ->where('bling_account', $staging->bling_account)
            ->first();

        if (!$dado) return false;

        $staging->update([
            'ml_tem_rebate' => $dado->tem_rebate,
            'ml_valor_rebate' => $dado->tem_rebate ? $dado->rebate : 0,
        ]);

        Log::info("ML planilha reprocessada automaticamente para pedido {$staging->numero_loja}");
        return true;
    }
}
