<?php

namespace App\Services;

use App\Models\ContaReceber;
use App\Models\Venda;
use Illuminate\Support\Facades\Log;

class ContaReceberService
{
    /**
     * Verifica se a venda está completa e gera conta a receber se ainda não tem.
     * Retorna true se gerou, false se já existia ou não está completa.
     */
    public static function gerarSeCompleta(Venda $venda): bool
    {
        // Já tem conta a receber?
        if (ContaReceber::where('id_venda', $venda->id_venda)->exists()) {
            return false;
        }

        // Verificar se está completa
        if (!self::vendaCompleta($venda)) {
            return false;
        }

        // Calcular repasse
        $canal = $venda->canal;
        $isMagalu = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'magalu');
        $isMlMe2Full = in_array($venda->ml_tipo_frete, ['ME2', 'FULL']);

        if ($isMagalu) {
            $repasse = (float) $venda->valor_total_venda - (float) $venda->comissao + (float) $venda->subsidio_pix;
        } else {
            $repasse = (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao;
        }

        ContaReceber::create([
            'id_venda' => $venda->id_venda,
            'valor_parcela' => round($repasse, 2),
            'data_vencimento' => $venda->data_venda,
            'status' => 'pendente',
            'numero_parcela' => 1,
            'total_parcelas' => 1,
            'forma_pagamento' => $canal?->nome_canal ?? 'Marketplace',
            'observacoes' => "Repasse #{$venda->numero_pedido_canal}",
            'lancamento_manual' => false,
        ]);

        Log::info("ContaReceber gerada para venda #{$venda->id_venda} ({$venda->numero_pedido_canal}) - R$ " . number_format($repasse, 2));

        return true;
    }

    /**
     * Verifica se a venda está completa (mesma lógica da dashboard).
     */
    private static function vendaCompleta(Venda $venda): bool
    {
        // Tem NF-e?
        if (empty($venda->nfe_chave_acesso)) {
            return false;
        }

        // Frete OK? (ML ME2/FULL não precisa, outros precisam de frete_pago)
        $isMlMe2Full = in_array($venda->ml_tipo_frete, ['ME2', 'FULL']);
        if (!$isMlMe2Full && !$venda->frete_pago) {
            return false;
        }

        // Planilha processada? (ML e Shopee precisam)
        $canal = $venda->canal;
        $isML = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'mercado');
        $isShopee = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'shopee');
        if (($isML || $isShopee) && !$venda->planilha_processada) {
            return false;
        }

        return true;
    }

    /**
     * Gera contas a receber em massa para todas as vendas completas sem conta.
     */
    public static function gerarEmMassa(): array
    {
        $vendas = Venda::with('canal')
            ->whereNotNull('nfe_chave_acesso')
            ->where('nfe_chave_acesso', '!=', '')
            ->whereDoesntHave('contasReceber')
            ->get();

        $geradas = 0;
        foreach ($vendas as $venda) {
            if (self::gerarSeCompleta($venda)) {
                $geradas++;
            }
        }

        return ['geradas' => $geradas];
    }
}
