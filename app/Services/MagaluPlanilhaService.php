<?php

namespace App\Services;

use App\Models\Venda;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MagaluPlanilhaService
{
    /**
     * Processa planilha financeira da Magalu e atualiza vendas.
     *
     * Colunas relevantes:
     * E  = Número do pedido (ex: LU-1524770104953718)
     * AB = Serviços do marketplace total (comissão %)
     * AF = Tarifa fixa (R$ 5,00 por item)
     * AN = Pago pelo Magalu - Desconto à Vista
     * AO = Pago por você (seller) - Desconto à Vista
     * AP = Pago pelo Magalu - Preço Promocional
     * AQ = Pago por você (seller) - Preço Promocional
     * AR = Subsídio Cupom - Pago pelo Magalu
     * AS = Subsídio Cupom - Pago por você (seller)
     * AT = Valor líquido estimado a receber
     */
    public static function processar(string $filePath): array
    {
        $resultado = ['atualizados' => 0, 'nao_encontrados' => 0, 'erros' => 0, 'detalhes' => []];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return ['atualizados' => 0, 'nao_encontrados' => 0, 'erros' => 1, 'detalhes' => ["Erro ao ler arquivo: {$e->getMessage()}"]];
        }

        $highestRow = $sheet->getHighestRow();
        Log::info("Magalu Planilha: {$highestRow} linhas");

        // Detectar linha do cabeçalho (procurar "Número do pedido")
        $linhaInicio = null;
        for ($i = 1; $i <= min(10, $highestRow); $i++) {
            $val = (string) $sheet->getCell("E{$i}")->getValue();
            if (stripos($val, 'pedido') !== false || stripos($val, 'mero') !== false) {
                $linhaInicio = $i + 1;
                break;
            }
        }

        if (!$linhaInicio) {
            // Tentar começar da linha 2 se não encontrar cabeçalho
            $linhaInicio = 2;
        }

        for ($i = $linhaInicio; $i <= $highestRow; $i++) {
            $numeroPedidoRaw = trim((string) $sheet->getCell("E{$i}")->getValue());
            if (empty($numeroPedidoRaw)) continue;

            // Remover prefixo "LU-" se existir
            $numeroPedido = preg_replace('/^LU-/i', '', $numeroPedidoRaw);

            try {
                $comissaoServicos = abs(self::parseDecimal($sheet->getCell("AB{$i}")->getValue()));
                $tarifaFixa = abs(self::parseDecimal($sheet->getCell("AF{$i}")->getValue()));
                $subsidioMagaluVista = self::parseDecimal($sheet->getCell("AN{$i}")->getValue());
                $descontoVendedorVista = abs(self::parseDecimal($sheet->getCell("AO{$i}")->getValue()));
                $subsidioMagaluPromo = self::parseDecimal($sheet->getCell("AP{$i}")->getValue());
                $descontoVendedorPromo = abs(self::parseDecimal($sheet->getCell("AQ{$i}")->getValue()));
                $subsidioCupomMagalu = self::parseDecimal($sheet->getCell("AR{$i}")->getValue());
                $descontoCupomVendedor = abs(self::parseDecimal($sheet->getCell("AS{$i}")->getValue()));
                $valorLiquido = self::parseDecimal($sheet->getCell("AT{$i}")->getValue());

                // Comissão real = serviços marketplace + tarifa fixa
                $comissaoReal = round($comissaoServicos + $tarifaFixa, 2);

                // Subsídio Magalu = valores que a Magalu paga ao vendedor (soma no repasse)
                // AN + AP + AR (valores positivos)
                $subsidiosMagalu = round(abs($subsidioMagaluVista) + abs($subsidioMagaluPromo) + abs($subsidioCupomMagalu), 2);

                // Repasse = Total pago + Subsídios Magalu - Comissão
                // O desconto à vista (AO) se anula com o subsídio (AN)
                // O preço promocional (AQ) já está embutido no preço do Bling

                // Buscar venda pelo número do pedido
                $venda = Venda::where('numero_pedido_canal', $numeroPedido)->first();

                if (!$venda) {
                    $resultado['nao_encontrados']++;
                    continue;
                }

                $venda->update([
                    'comissao' => $comissaoReal,
                    'planilha_processada' => true,
                ]);

                VendaRecalculoService::recalcularMargens($venda);
                $resultado['atualizados']++;

            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = "Pedido {$numeroPedido}: {$e->getMessage()}";
                Log::error("Magalu planilha erro", ['pedido' => $numeroPedido, 'error' => $e->getMessage()]);
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        Log::info("Magalu Planilha: Concluído", $resultado);
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
