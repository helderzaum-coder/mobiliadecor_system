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
    public static function calcular(int $canalId, array $itens, ?string $mlTipoAnuncio = null, ?string $mlTipoFrete = null, float $valorFrete = 0): array
    {
        $canal = CanalVenda::find($canalId);
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

        // Separar regras cumulativas das exclusivas (faixa)
        $regrasCumulativas = $regras->where('cumulativa', true);
        $regrasExclusivas = $regras->where('cumulativa', false);

        // Priorizar regras mais específicas (com ml_tipo_anuncio e ml_tipo_frete preenchidos)
        $regrasExclusivas = $regrasExclusivas->sortByDesc(function ($regra) {
            return (int) !is_null($regra->ml_tipo_anuncio) + (int) !is_null($regra->ml_tipo_frete);
        });

        $comissaoTotal = 0;
        $subsidioPixTotal = 0;
        $detalhes = [];

        // Se canal tem faixas e comissao_sobre_frete, calcular sobre o total do pedido
        $temFaixas = $regrasExclusivas->contains(fn ($r) => $r->faixa_valor_max > 0);
        $comissaoSobreFrete = (bool) ($canal->comissao_sobre_frete ?? false);

        $totalProdutos = array_sum(array_map(
            fn ($i) => (float) ($i['valor'] ?? 0) * (int) ($i['quantidade'] ?? 1),
            $itens
        ));
        $totalPedido = $totalProdutos + $valorFrete;

        if ($temFaixas && $comissaoSobreFrete && $valorFrete > 0) {
            $itensCalculo = [[
                'descricao' => 'Total do pedido (produtos + frete)',
                'codigo' => 'TOTAL',
                'valor' => $totalPedido,
                'quantidade' => 1,
            ]];
        } else {
            $itensCalculo = $itens;
        }

        foreach ($itensCalculo as $item) {
            $valorItem = (float) ($item['valor'] ?? 0);
            $quantidade = (int) ($item['quantidade'] ?? 1);

            // Coletar todas as regras aplicáveis (faixas progressivas)
            $regrasAplicaveis = [];
            foreach ($regrasExclusivas as $regra) {
                $min = (float) ($regra->faixa_valor_min ?? 0);

                if ($valorItem > $min) {
                    $regrasAplicaveis[] = $regra;
                }
            }

            if (empty($regrasAplicaveis)) {
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

            $comissaoUnit = 0;
            $subsidioPixUnit = 0;
            $nomeRegra = '';

            if (count($regrasAplicaveis) === 1 && empty($regrasAplicaveis[0]->faixa_valor_max)) {
                // Regra única sem faixa — aplicar direto
                $regra = $regrasAplicaveis[0];
                $comissaoUnit = ($valorItem * $regra->percentual / 100) + (float) $regra->valor_fixo;
                $subsidioPixUnit = $valorItem * (float) $regra->subsidio_pix / 100;
                $nomeRegra = $regra->nome_regra;
            } else {
                // Faixas exclusivas: usar a faixa onde o valor se encaixa
                $regraSelecionada = null;
                foreach ($regrasAplicaveis as $regra) {
                    $min = (float) ($regra->faixa_valor_min ?? 0);
                    $max = (float) ($regra->faixa_valor_max ?? PHP_FLOAT_MAX);
                    if ($valorItem > $min && $valorItem <= $max) {
                        $regraSelecionada = $regra;
                    }
                }
                if (!$regraSelecionada) {
                    $regraSelecionada = end($regrasAplicaveis);
                }

                $min = (float) ($regraSelecionada->faixa_valor_min ?? 0);
                $baseExcedente = $valorItem - $min;
                $comissaoUnit = ($baseExcedente * $regraSelecionada->percentual / 100) + (float) $regraSelecionada->valor_fixo;
                $subsidioPixUnit = $baseExcedente * (float) $regraSelecionada->subsidio_pix / 100;
                $nomeRegra = $regraSelecionada->nome_regra;
            }

            $comissaoItem = $comissaoUnit * $quantidade;
            $subsidioPixItem = $subsidioPixUnit * $quantidade;

            $comissaoTotal += $comissaoItem;
            $subsidioPixTotal += $subsidioPixItem;

            $detalhes[] = [
                'item' => $item['descricao'] ?? $item['codigo'] ?? '?',
                'valor' => $valorItem,
                'quantidade' => $quantidade,
                'regra' => $nomeRegra,
                'comissao_unitaria' => round($comissaoUnit, 2),
                'subsidio_pix_unitario' => round($subsidioPixUnit, 2),
                'comissao_total' => round($comissaoItem, 2),
                'subsidio_pix_total' => round($subsidioPixItem, 2),
            ];
        }

        // Aplicar regras cumulativas sobre o total do pedido
        foreach ($regrasCumulativas as $regra) {
            $baseCumulativa = $comissaoSobreFrete ? $totalPedido : $totalProdutos;
            $comissaoCum = ($baseCumulativa * $regra->percentual / 100) + (float) $regra->valor_fixo;
            $subsidioCum = $baseCumulativa * (float) $regra->subsidio_pix / 100;

            $comissaoTotal += $comissaoCum;
            $subsidioPixTotal += $subsidioCum;

            $detalhes[] = [
                'item' => 'Taxa cumulativa',
                'valor' => $baseCumulativa,
                'quantidade' => 1,
                'regra' => $regra->nome_regra,
                'comissao_unitaria' => round($comissaoCum, 2),
                'subsidio_pix_unitario' => round($subsidioCum, 2),
                'comissao_total' => round($comissaoCum, 2),
                'subsidio_pix_total' => round($subsidioCum, 2),
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
