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
     * AE = Serviços do marketplace total (comissão %)
     * AR = Tarifa fixa (R$ 5,00 por item)
     * AZ = Pago pelo Magalu - Desconto à Vista
     * BA = Pago por você (seller) - Desconto à Vista
     * BB = Pago pelo Magalu - Preço Promocional
     * BC = Pago por você (seller) - Preço Promocional
     * BD = Subsídio Cupom - Pago pelo Magalu
     * BE = Subsídio Cupom - Pago por você (seller)
     * BF = Valor líquido estimado a receber
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
                $comissaoServicos = abs(self::parseDecimal($sheet->getCell("AE{$i}")->getValue()));
                $comissaoServicos2 = abs(self::parseDecimal($sheet->getCell("AL{$i}")->getValue()));
                $tarifaFixa = abs(self::parseDecimal($sheet->getCell("AR{$i}")->getValue()));
                $subsidioMagaluVista = self::parseDecimal($sheet->getCell("AZ{$i}")->getValue());
                $descontoVendedorVista = abs(self::parseDecimal($sheet->getCell("BA{$i}")->getValue()));
                $subsidioMagaluPromo = self::parseDecimal($sheet->getCell("BB{$i}")->getValue());
                $descontoVendedorPromo = abs(self::parseDecimal($sheet->getCell("BC{$i}")->getValue()));
                $subsidioCupomMagalu = self::parseDecimal($sheet->getCell("BD{$i}")->getValue());
                $descontoCupomVendedor = abs(self::parseDecimal($sheet->getCell("BE{$i}")->getValue()));
                $valorLiquido = self::parseDecimal($sheet->getCell("BF{$i}")->getValue());

                // Comissão real = serviços marketplace (forma 1 + forma 2) + tarifa fixa
                $comissaoReal = round($comissaoServicos + $comissaoServicos2 + $tarifaFixa, 2);

                // Descontos pagos pelo seller (Promo, Vista, Cupom)
                // Esses valores REDUZEM o repasse — a Magalu desconta do vendedor
                $descontosVendedor = round($descontoVendedorVista + $descontoVendedorPromo + $descontoCupomVendedor, 2);

                // Subsídio que a Magalu devolve (coluna AZ) — recebido em repasse separado
                $subsidioMagalu = round(abs($subsidioMagaluVista) + abs($subsidioMagaluPromo) + abs($subsidioCupomMagalu), 2);

                // Buscar venda pelo número do pedido
                $venda = Venda::where('numero_pedido_canal', $numeroPedido)->first();

                if (!$venda) {
                    // Tentar no staging (pedido ainda não aprovado)
                    $staging = \App\Models\PedidoBlingStaging::where('numero_loja', $numeroPedido)
                        ->orWhere('numero_loja', $numeroPedidoRaw)
                        ->whereNotNull('bling_id')
                        ->first();

                    if ($staging) {
                        $staging->update([
                            'comissao_calculada' => $comissaoReal,
                            'subsidio_pix' => $descontosVendedor,
                            'planilha_shopee' => true,
                        ]);

                        $resultado['atualizados']++;
                        continue;
                    }

                    $resultado['nao_encontrados']++;
                    continue;
                }

                $venda->update([
                    'comissao' => $comissaoReal,
                    'subsidio_pix' => $descontosVendedor,
                    'subsidio_magalu' => $subsidioMagalu,
                    'planilha_processada' => true,
                ]);

                // Gerar conta a receber separada para o subsídio Magalu
                if ($subsidioMagalu > 0) {
                    $jaExiste = \App\Models\ContaReceber::where('id_venda', $venda->id_venda)
                        ->where('observacoes', 'like', '%Subsídio Magalu%')
                        ->exists();

                    if (!$jaExiste) {
                        \App\Models\ContaReceber::create([
                            'id_venda' => $venda->id_venda,
                            'valor_parcela' => $subsidioMagalu,
                            'data_vencimento' => $venda->data_venda,
                            'status' => 'pendente',
                            'numero_parcela' => 1,
                            'total_parcelas' => 1,
                            'forma_pagamento' => 'Magalu - Subsídio',
                            'observacoes' => "Subsídio Magalu (desconto à vista/promo/cupom) — Pedido #{$venda->numero_pedido_canal}",
                            'lancamento_manual' => false,
                        ]);
                    }
                }

                // Gerar conta a receber principal se não existir
                $contaPrincipal = \App\Models\ContaReceber::where('id_venda', $venda->id_venda)
                    ->where('forma_pagamento', 'not like', '%Subsídio%')
                    ->first();

                if (!$contaPrincipal) {
                    $repasseCalc = (float) $venda->valor_total_venda - $comissaoReal - (float) ($venda->comissao_afiliado ?? 0);
                    \App\Models\ContaReceber::create([
                        'id_venda' => $venda->id_venda,
                        'valor_parcela' => round($repasseCalc, 2),
                        'data_vencimento' => $venda->data_venda,
                        'status' => 'pendente',
                        'numero_parcela' => 1,
                        'total_parcelas' => 1,
                        'forma_pagamento' => $venda->canal?->nome_canal ?? 'Magalu',
                        'observacoes' => "Repasse #{$venda->numero_pedido_canal}",
                        'lancamento_manual' => false,
                    ]);
                }

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

    /**
     * Reprocessa planilha aplicando APENAS o subsídio Magalu (AZ+BB+BD)
     * em vendas já processadas que não tinham esse campo preenchido.
     */
    public static function reprocessarSubsidios(string $filePath): array
    {
        $resultado = ['atualizados' => 0, 'nao_encontrados' => 0, 'erros' => 0];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Exception $e) {
            return ['atualizados' => 0, 'nao_encontrados' => 0, 'erros' => 1];
        }

        $highestRow = $sheet->getHighestRow();

        $linhaInicio = null;
        for ($i = 1; $i <= min(10, $highestRow); $i++) {
            $val = (string) $sheet->getCell("E{$i}")->getValue();
            if (stripos($val, 'pedido') !== false || stripos($val, 'mero') !== false) {
                $linhaInicio = $i + 1;
                break;
            }
        }
        if (!$linhaInicio) $linhaInicio = 2;

        for ($i = $linhaInicio; $i <= $highestRow; $i++) {
            $numeroPedidoRaw = trim((string) $sheet->getCell("E{$i}")->getValue());
            if (empty($numeroPedidoRaw)) continue;

            $numeroPedido = preg_replace('/^LU-/i', '', $numeroPedidoRaw);

            try {
                $subsidioMagaluVista = self::parseDecimal($sheet->getCell("AZ{$i}")->getValue());
                $subsidioMagaluPromo = self::parseDecimal($sheet->getCell("BB{$i}")->getValue());
                $subsidioCupomMagalu = self::parseDecimal($sheet->getCell("BD{$i}")->getValue());
                $subsidioMagalu = round(abs($subsidioMagaluVista) + abs($subsidioMagaluPromo) + abs($subsidioCupomMagalu), 2);

                if ($subsidioMagalu <= 0) continue;

                $venda = Venda::where('numero_pedido_canal', $numeroPedido)->first();
                if (!$venda) {
                    $resultado['nao_encontrados']++;
                    continue;
                }

                // Pular se já tem subsídio
                if ((float) ($venda->subsidio_magalu ?? 0) > 0) continue;

                $venda->update(['subsidio_magalu' => $subsidioMagalu]);

                // Gerar conta a receber separada
                $jaExiste = \App\Models\ContaReceber::where('id_venda', $venda->id_venda)
                    ->where('observacoes', 'like', '%Subsídio Magalu%')
                    ->exists();

                if (!$jaExiste) {
                    \App\Models\ContaReceber::create([
                        'id_venda' => $venda->id_venda,
                        'valor_parcela' => $subsidioMagalu,
                        'data_vencimento' => $venda->data_venda,
                        'status' => 'pendente',
                        'numero_parcela' => 1,
                        'total_parcelas' => 1,
                        'forma_pagamento' => 'Magalu - Subsídio',
                        'observacoes' => "Subsídio Magalu (desconto à vista/promo/cupom) — Pedido #{$venda->numero_pedido_canal}",
                        'lancamento_manual' => false,
                    ]);
                }

                VendaRecalculoService::recalcularMargens($venda);
                $resultado['atualizados']++;

            } catch (\Exception $e) {
                $resultado['erros']++;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $resultado;
    }
}
