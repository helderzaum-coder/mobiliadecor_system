<?php

namespace App\Services;

use App\Models\Transportadora;
use App\Models\TransportadoraTabelaFrete;
use App\Models\TransportadoraTaxa;
use Illuminate\Support\Collection;

class CotacaoFreteService
{
    /**
     * Cota frete para todas as transportadoras ativas que atendem a UF.
     *
     * @return array Lista de cotações ordenadas por valor total
     */
    public static function cotar(
        string $destUf,
        string $destCep,
        float $pesoBruto,
        float $valorNf,
        ?string $destCidade = null
    ): array {
        $cepNumerico = preg_replace('/\D/', '', $destCep);
        $cotacoes = [];

        // Buscar transportadoras ativas que atendem a UF (via UFs cadastradas OU faixas de frete)
        $transportadoras = Transportadora::where('ativo', true)
            ->where(function ($q) use ($destUf) {
                $q->whereHas('ufsAtendidas', fn ($q2) => $q2->where('uf', $destUf))
                  ->orWhereHas('tabelaFrete', fn ($q2) => $q2->where('uf', $destUf));
            })
            ->get();

        foreach ($transportadoras as $transp) {
            $cotacao = self::calcularCotacao($transp, $destUf, $cepNumerico, $pesoBruto, $valorNf, $destCidade);
            if ($cotacao) {
                $cotacoes[] = $cotacao;
            }
        }

        // Ordenar por valor total
        usort($cotacoes, fn ($a, $b) => $a['total'] <=> $b['total']);

        return $cotacoes;
    }

    private static function calcularCotacao(
        Transportadora $transp,
        string $uf,
        string $cep,
        float $peso,
        float $valorNf,
        ?string $cidade
    ): ?array {
        // 1. Encontrar faixa na tabela de frete
        $faixa = self::encontrarFaixa($transp->id_transportadora, $uf, $cep, $peso);

        if (!$faixa) {
            return null; // Transportadora não tem faixa para este destino/peso
        }

        // 2. Calcular valor base do frete
        $valorFrete = 0;
        $valorFixo = (float) ($faixa->valor_fixo ?? 0);
        $valorKg = (float) ($faixa->valor_kg ?? 0);

        if ($valorFixo > 0 && $valorKg > 0) {
            // Faixa com fixo + excedente por kg (ex: acima de 100kg)
            $pesoExcedente = $peso - (float) $faixa->peso_min;
            $valorFrete = $valorFixo + ($pesoExcedente * $valorKg);
        } elseif ($valorFixo > 0) {
            // Só valor fixo (faixas até 100kg)
            $valorFrete = $valorFixo;
        } elseif ($valorKg > 0) {
            // Só por kg
            $valorFrete = $peso * $valorKg;
        }

        // 3. Despacho (faixa > transportadora)
        $despacho = (float) ($faixa->despacho ?? $transp->taxa_despacho);

        // 4. Pedágio (faixa > transportadora)
        $pedagio = 0;
        $pedagioValor = (float) ($faixa->pedagio_valor ?? $transp->pedagio_valor);
        $pedagioFracao = (float) ($faixa->pedagio_fracao_kg ?? $transp->pedagio_fracao_kg);
        if ($pedagioValor > 0 && $pedagioFracao > 0) {
            $fracoes = ceil($peso / $pedagioFracao);
            $pedagio = $fracoes * $pedagioValor;
        }

        // 5. Ad Valorem (faixa > transportadora)
        $advalorem = 0;
        $advPerc = (float) ($faixa->adv_percentual ?? $transp->adv_percentual);
        $advMin = (float) ($faixa->adv_minimo ?? $transp->adv_minimo);
        if ($advPerc > 0 && $valorNf > 0) {
            $advalorem = $valorNf * ($advPerc / 100);
            if ($advMin > 0 && $advalorem < $advMin) {
                $advalorem = $advMin;
            }
        }

        // 6. GRIS (faixa > transportadora)
        $gris = 0;
        $grisPerc = (float) ($faixa->gris_percentual ?? $transp->gris_percentual);
        $grisMin = (float) ($faixa->gris_minimo ?? $transp->gris_minimo);
        if ($grisPerc > 0 && $valorNf > 0) {
            $gris = $valorNf * ($grisPerc / 100);
            if ($grisMin > 0 && $gris < $grisMin) {
                $gris = $grisMin;
            }
        }

        // 7. Taxas especiais (TDA, TRT, TAS) por CEP/cidade
        $taxasEspeciais = self::calcularTaxasEspeciais($transp->id_transportadora, $uf, $cep, $cidade, $valorFrete);

        // Total
        $total = $valorFrete + $despacho + $pedagio + $advalorem + $gris + $taxasEspeciais['total'];

        return [
            'id_transportadora' => $transp->id_transportadora,
            'nome' => $transp->nome_transportadora,
            'uf_faixa' => $faixa->uf ?? $uf,
            'regiao' => $faixa->regiao ?? '-',
            'frete_peso' => round($valorFrete, 2),
            'despacho' => round($despacho, 2),
            'pedagio' => round($pedagio, 2),
            'advalorem' => round($advalorem, 2),
            'gris' => round($gris, 2),
            'taxas_especiais' => $taxasEspeciais['detalhes'],
            'taxas_especiais_total' => round($taxasEspeciais['total'], 2),
            'total' => round($total, 2),
        ];
    }

    private static function encontrarFaixa(int $idTransportadora, string $uf, string $cep, float $peso): ?TransportadoraTabelaFrete
    {
        // Buscar faixa mais específica: UF + CEP + peso
        $query = TransportadoraTabelaFrete::where('id_transportadora', $idTransportadora)
            ->where('peso_min', '<=', $peso)
            ->where('peso_max', '>=', $peso);

        // Tentar com UF + faixa de CEP
        $faixa = (clone $query)
            ->where('uf', $uf)
            ->whereNotNull('cep_inicio')
            ->whereNotNull('cep_fim')
            ->where(function ($q) use ($cep) {
                // Suportar faixas onde cep_inicio > cep_fim (cadastro invertido)
                $q->where(function ($q2) use ($cep) {
                    $q2->where('cep_inicio', '<=', $cep)->where('cep_fim', '>=', $cep);
                })->orWhere(function ($q2) use ($cep) {
                    $q2->where('cep_fim', '<=', $cep)->where('cep_inicio', '>=', $cep);
                });
            })
            ->first();

        if ($faixa) return $faixa;

        // Tentar com UF sem CEP
        $faixa = (clone $query)
            ->where('uf', $uf)
            ->whereNull('cep_inicio')
            ->first();

        if ($faixa) return $faixa;

        // Tentar sem UF (genérica) com CEP
        $faixa = (clone $query)
            ->whereNull('uf')
            ->whereNotNull('cep_inicio')
            ->whereNotNull('cep_fim')
            ->where(function ($q) use ($cep) {
                $q->where(function ($q2) use ($cep) {
                    $q2->where('cep_inicio', '<=', $cep)->where('cep_fim', '>=', $cep);
                })->orWhere(function ($q2) use ($cep) {
                    $q2->where('cep_fim', '<=', $cep)->where('cep_inicio', '>=', $cep);
                });
            })
            ->first();

        if ($faixa) return $faixa;

        // Tentar genérica total
        return (clone $query)
            ->whereNull('uf')
            ->whereNull('cep_inicio')
            ->first();
    }

    private static function calcularTaxasEspeciais(int $idTransportadora, string $uf, string $cep, ?string $cidade, float $valorFrete): array
    {
        $detalhes = [];
        $total = 0;

        $taxas = TransportadoraTaxa::where('id_transportadora', $idTransportadora)
            ->where(function ($q) use ($uf) {
                $q->where('uf', $uf)->orWhereNull('uf');
            })
            ->get();

        foreach ($taxas as $taxa) {
            // Verificar se a taxa se aplica ao CEP
            if ($taxa->cep_inicio && $taxa->cep_fim) {
                $min = min($taxa->cep_inicio, $taxa->cep_fim);
                $max = max($taxa->cep_inicio, $taxa->cep_fim);
                if ($cep < $min || $cep > $max) {
                    continue;
                }
            }

            // Verificar cidade
            if ($taxa->cidade && $cidade) {
                if (mb_strtolower($taxa->cidade) !== mb_strtolower($cidade)) {
                    continue;
                }
            } elseif ($taxa->cidade && !$cidade) {
                continue; // Taxa é por cidade mas não temos a cidade do pedido
            }

            $valor = 0;
            if ($taxa->valor_fixo && $taxa->valor_fixo > 0) {
                $valor = (float) $taxa->valor_fixo;
            } elseif ($taxa->percentual && $taxa->percentual > 0) {
                $valor = $valorFrete * ((float) $taxa->percentual / 100);
            }

            if ($valor > 0) {
                $detalhes[] = [
                    'tipo' => $taxa->tipo_taxa,
                    'valor' => round($valor, 2),
                    'obs' => $taxa->observacao,
                ];
                $total += $valor;
            }
        }

        return ['detalhes' => $detalhes, 'total' => $total];
    }
}
