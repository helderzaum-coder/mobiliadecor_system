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

        // Verificar se está completa OU aguardando envio com custo
        if (!self::vendaCompleta($venda) && !self::vendaAguardandoEnvio($venda)) {
            return false;
        }

        // Calcular repasse
        $canal = $venda->canal;
        $isMagalu = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'magalu');
        $isShopee = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'shopee');
        $isML = $canal && (str_contains(strtolower($canal->nome_canal ?? ''), 'mercado') || str_starts_with($venda->numero_pedido_canal ?? '', '2000'));

        if ($isMagalu) {
            $repasse = (float) $venda->valor_total_venda - (float) $venda->comissao - (float) ($venda->comissao_afiliado ?? 0);
        } else {
            $repasse = (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao - (float) ($venda->comissao_afiliado ?? 0);
        }

        // ML ME1: o frete é cobrado pelo ML do vendedor (desconta do repasse)
        if ($isML && !in_array($venda->ml_tipo_frete, ['ME2', 'FULL'])) {
            $mlFreteCusto = (float) ($venda->ml_frete_custo ?? 0);
            if ($mlFreteCusto > 0) {
                $repasse -= $mlFreteCusto;
            }
            // Rebate/estorno: se ml_sale_fee > 0, o rebate NÃO está descontado da sale_fee
            $mlRebate = (float) ($venda->ml_valor_rebate ?? 0);
            if ($mlRebate > 0) {
                $repasse += $mlRebate;
            }
        }

        // Subsídio pix: para canais onde o marketplace repassa o subsídio ao vendedor (exceto Shopee e Magalu)
        $subsidioPix = (float) ($venda->subsidio_pix ?? 0);
        if ($subsidioPix > 0 && !$isShopee && !$isMagalu) {
            $repasse += $subsidioPix;
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

        // Planilha afiliado processada? (Shopee precisa)
        if ($isShopee && !$venda->planilha_afiliado_processada) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se a venda está aguardando envio (com custo > 0).
     * Permite gerar conta a receber mesmo sem NF-e.
     */
    private static function vendaAguardandoEnvio(Venda $venda): bool
    {
        return !empty($venda->data_prevista_envio) && (float) $venda->custo_produtos > 0;
    }

    /**
     * Regenera a conta a receber de uma venda quando comissao_afiliado muda.
     * Subtrai o afiliado do valor existente, ou recria se não existe.
     */
    public static function regenerar(Venda $venda): bool
    {
        $contaExistente = ContaReceber::where('id_venda', $venda->id_venda)
            ->where('forma_pagamento', 'not like', '%Subsídio%')
            ->where('lancamento_manual', false)
            ->first();

        $afiliado = (float) ($venda->comissao_afiliado ?? 0);
        $canal = $venda->canal;
        $isMagalu = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'magalu');
        $isShopee = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'shopee');

        $isML = $canal && (str_contains(strtolower($canal->nome_canal ?? ''), 'mercado') || str_starts_with($venda->numero_pedido_canal ?? '', '2000'));

        if ($isMagalu) {
            $repasseBase = (float) $venda->valor_total_venda - (float) $venda->comissao;
        } else {
            $repasseBase = (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao;
        }

        $repasse = round($repasseBase - $afiliado, 2);

        // ML ME1: o frete é cobrado pelo ML do vendedor (desconta do repasse)
        if ($isML && !in_array($venda->ml_tipo_frete, ['ME2', 'FULL'])) {
            $mlFreteCusto = (float) ($venda->ml_frete_custo ?? 0);
            if ($mlFreteCusto > 0) {
                $repasse -= $mlFreteCusto;
            }
            $mlRebate = (float) ($venda->ml_valor_rebate ?? 0);
            if ($mlRebate > 0) {
                $repasse += $mlRebate;
            }
        }

        // Subsídio pix: para canais onde o marketplace repassa ao vendedor (exceto Shopee e Magalu)
        $subsidioPix = (float) ($venda->subsidio_pix ?? 0);
        if ($subsidioPix > 0 && !$isShopee && !$isMagalu) {
            $repasse += $subsidioPix;
        }

        if ($contaExistente) {
            $contaExistente->update(['valor_parcela' => $repasse]);
            return true;
        }

        // Se não existe, gerar normalmente
        return self::gerarSeCompleta($venda);
    }

    /**
     * Gera contas a receber em massa para todas as vendas completas sem conta.
     */
    public static function gerarEmMassa(): array
    {
        $vendas = Venda::with('canal')
            ->where(function ($q) {
                $q->where(fn ($q2) => $q2->whereNotNull('nfe_chave_acesso')->where('nfe_chave_acesso', '!=', ''))
                  ->orWhere(fn ($q2) => $q2->whereNotNull('data_prevista_envio')->where('custo_produtos', '>', 0));
            })
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
