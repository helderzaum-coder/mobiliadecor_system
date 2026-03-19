<?php

namespace App\Services;

use App\Models\TransportadoraTabelaFrete;
use App\Models\TransportadoraTaxa;

class TransportadoraExportService
{
    /**
     * Exporta tabela de frete como CSV (mesmo formato da importação).
     * Colunas: uf;cep_inicio;cep_fim;regiao;peso_min;peso_max;valor_kg;valor_fixo;frete_minimo;despacho;pedagio_valor;pedagio_fracao_kg;adv_percentual;adv_minimo;gris_percentual;gris_minimo
     */
    public static function exportarTabelaFrete(int $idTransportadora): string
    {
        $header = 'uf;cep_inicio;cep_fim;regiao;peso_min;peso_max;valor_kg;valor_fixo;frete_minimo;despacho;pedagio_valor;pedagio_fracao_kg;adv_percentual;adv_minimo;gris_percentual;gris_minimo';

        $linhas = [$header];

        TransportadoraTabelaFrete::where('id_transportadora', $idTransportadora)
            ->orderBy('uf')
            ->orderBy('cep_inicio')
            ->orderBy('peso_min')
            ->cursor()
            ->each(function ($f) use (&$linhas) {
                $linhas[] = implode(';', [
                    $f->uf ?? '',
                    $f->cep_inicio ?? '',
                    $f->cep_fim ?? '',
                    $f->regiao ?? '',
                    self::fmt($f->peso_min, 3),
                    self::fmt($f->peso_max, 3),
                    self::fmt($f->valor_kg),
                    self::fmt($f->valor_fixo),
                    self::fmt($f->frete_minimo),
                    self::fmt($f->despacho),
                    self::fmt($f->pedagio_valor),
                    self::fmt($f->pedagio_fracao_kg),
                    self::fmt($f->adv_percentual, 4),
                    self::fmt($f->adv_minimo),
                    self::fmt($f->gris_percentual, 4),
                    self::fmt($f->gris_minimo),
                ]);
            });

        return implode("\n", $linhas);
    }

    /**
     * Exporta taxas especiais como CSV (mesmo formato da importação).
     * Colunas: tipo_taxa;uf;cidade;cep_inicio;cep_fim;valor_fixo;percentual;observacao
     */
    public static function exportarTaxas(int $idTransportadora): string
    {
        $header = 'tipo_taxa;uf;cidade;cep_inicio;cep_fim;valor_fixo;percentual;observacao';

        $linhas = [$header];

        TransportadoraTaxa::where('id_transportadora', $idTransportadora)
            ->orderBy('tipo_taxa')
            ->orderBy('uf')
            ->orderBy('cep_inicio')
            ->cursor()
            ->each(function ($t) use (&$linhas) {
                $linhas[] = implode(';', [
                    $t->tipo_taxa ?? '',
                    $t->uf ?? '',
                    $t->cidade ?? '',
                    $t->cep_inicio ?? '',
                    $t->cep_fim ?? '',
                    self::fmt($t->valor_fixo),
                    self::fmt($t->percentual, 4),
                    $t->observacao ?? '',
                ]);
            });

        return implode("\n", $linhas);
    }

    private static function fmt($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') return '';
        return number_format((float) $value, $decimals, ',', '');
    }
}
