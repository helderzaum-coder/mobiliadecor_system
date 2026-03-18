<?php

namespace App\Services;

use App\Models\CanalVenda;
use App\Models\RegraComissao;

class CalculoComissaoService
{
    /**
     * Calcula a comissão total de um pedido baseado nos itens
     *
     * @param int $canalId ID do canal de venda
     * @param array $itens Array de itens [['valor' => 100, 'quantidade' => 1], ...]
     * @param string|null $mlTipoAnuncio Tipo de anúncio ML (Clássico/Premium)
     * @param string|null $mlTipoFrete Tipo de frete ML (ME1/ME2/FULL)
     * @return array ['comissao_total', 'subsidio_pix_total', 'detalhes' => [...]]
     */
    public static function calcular(int $canalId, array $itens, ?string $mlTipoAnuncio = null, ?string $mlTipoFrete = null): array
    {
        $query = RegraComissao::where('id_canal', $canalId)
            ->where('ativo', true)
            ->orderBy('faixa_valor_min', 'asc');

        // Filtrar por tipo de anúncio ML se informado
        if ($mlTipoAnuncio) {
            $query->where(function ($q) use ($mlTipoAnuncio) {
                $q->where('ml_tipo_anuncio', $mlTipoAnuncio)
                  ->orWhereNull('ml_tipo_anuncio');
            });
        }

        // Filtrar por tipo de frete ML se informado
        if ($mlTipoFrete) {
            $query->where(function ($q) use ($mlTipoFrete) {
                $q->where('ml_tipo_frete', $mlTipoFrete)
                  ->orWhereNull('ml_tipo_frete');
            });
        }

        $regras = $query->get();

        // Priorizar regras mais específicas (com ml_tipo_anuncio e ml_tipo_frete preenchidos)
        $regras = $regras->sortByDesc(function ($regra) {
            return (int) !is_null($regra->ml_tipo_anuncio) + (int) !is_null($regra->ml_tipo_frete);
        });

        $comissaoTotal = 0;
        $subsidioPixTotal = 0;
        $detalhes = [];

        foreach ($itens as $item) {
            $valorItem = (float) ($item['valor'] ?? 0);
            $quantidade = (int) ($item['quantidade'] ?? 1);

            // Encontrar a regra que se aplica a este valor
            $regraAplicavel = null;
            foreach ($regras as $regra) {
                $min = (float) ($regra->faixa_valor_min ?? 0);
                $max = (float) ($regra->faixa_valor_max ?? PHP_FLOAT_MAX);

                if ($valorItem >= $min && $valorItem <= $max) {
                    $regraAplicavel = $regra;
                    break;
                }
            }

            if (!$regraAplicavel) {
                $detalhes[] = [
                    'item' => $item['descricao'] ?? $item['codigo'] ?? '?',
                    'valor' => $valorItem,
                    'quantidade' => $quantidade,
                    'regra' => 'Nenhuma regra encontrada',
                    'comissao_unitaria' => 0,
                    'subsidio_pix_unitario' => 0,
                    'comissao_total' => 0,
                    'subsidio_pix_total' => 0,
                ];
                continue;
            }

            // Comissão = (percentual% do valor) + valor_fixo
            $comissaoUnit = ($valorItem * $regraAplicavel->percentual / 100)
                          + (float) $regraAplicavel->valor_fixo;

            // Subsídio pix = percentual sobre o valor do item
            $subsidioPixUnit = $valorItem * (float) $regraAplicavel->subsidio_pix / 100;

            $comissaoItem = $comissaoUnit * $quantidade;
            $subsidioPixItem = $subsidioPixUnit * $quantidade;

            $comissaoTotal += $comissaoItem;
            $subsidioPixTotal += $subsidioPixItem;

            $detalhes[] = [
                'item' => $item['descricao'] ?? $item['codigo'] ?? '?',
                'valor' => $valorItem,
                'quantidade' => $quantidade,
                'regra' => $regraAplicavel->nome_regra,
                'comissao_unitaria' => round($comissaoUnit, 2),
                'subsidio_pix_unitario' => round($subsidioPixUnit, 2),
                'comissao_total' => round($comissaoItem, 2),
                'subsidio_pix_total' => round($subsidioPixItem, 2),
            ];
        }

        return [
            'comissao_total' => round($comissaoTotal, 2),
            'subsidio_pix_total' => round($subsidioPixTotal, 2),
            'detalhes' => $detalhes,
        ];
    }

    /**
     * Calcula o valor do imposto baseado no tipo de nota do canal
     */
    public static function calcularBaseImposto(
        string $tipoNota,
        float $totalPedido,
        float $frete,
        float $percentualImposto
    ): array {
        $baseCalculo = match ($tipoNota) {
            'cheia' => $totalPedido,
            'produto' => $totalPedido - $frete,
            'meia_nota' => ($totalPedido - $frete) / 2,
            default => $totalPedido,
        };

        $valorImposto = round($baseCalculo * $percentualImposto / 100, 2);

        return [
            'base_calculo' => round($baseCalculo, 2),
            'percentual' => $percentualImposto,
            'valor_imposto' => $valorImposto,
            'tipo_nota' => $tipoNota,
        ];
    }
}
