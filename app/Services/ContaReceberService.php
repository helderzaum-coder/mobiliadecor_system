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
        $isMM = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'madeira');

        if ($isMagalu) {
            $repasse = (float) $venda->valor_total_venda - (float) $venda->comissao - (float) ($venda->comissao_afiliado ?? 0);
        } elseif ($isML && (float) ($venda->ml_sale_fee ?? 0) > 0) {
            // ML com dados da API: usar campos ML diretamente
            $mlSaleFee = (float) $venda->ml_sale_fee;
            $mlFreteCusto = (float) ($venda->ml_frete_custo ?? 0);
            $mlFreteReceita = (float) ($venda->ml_frete_receita ?? 0);
            $mlRebate = (float) ($venda->ml_valor_rebate ?? 0);
            if (in_array($venda->ml_tipo_frete, ['ME2', 'FULL'])) {
                // ME2/FULL: repasse = total_produtos - sale_fee - frete_liquido
                $freteLiquido = $mlFreteCusto > 0 ? $mlFreteCusto - $mlFreteReceita : 0;
                $repasse = (float) $venda->total_produtos - $mlSaleFee - $freteLiquido - (float) ($venda->comissao_afiliado ?? 0);
            } else {
                // ME1: repasse = total_produtos + frete_receita - sale_fee (ml_sale_fee já é líquido do rebate)
                $repasse = (float) $venda->total_produtos + $mlFreteReceita - $mlSaleFee - (float) ($venda->comissao_afiliado ?? 0);
            }
        } else {
            $repasse = (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao - (float) ($venda->comissao_afiliado ?? 0);
        }

        // Subsídio pix: para canais onde o marketplace repassa o subsídio ao vendedor (exceto Shopee e Magalu)
        $subsidioPix = (float) ($venda->subsidio_pix ?? 0);
        if ($subsidioPix > 0 && !$isShopee && !$isMagalu) {
            $repasse += $subsidioPix;
        }

        // MM com parcelas: criar múltiplas ContaReceber
        $totalParcelas = 1;
        if ($isMM) {
            $dadoMM = \App\Models\PlanilhaMmDado::where('numero_pedido', $venda->numero_pedido_canal)->first();
            if ($dadoMM && $dadoMM->parcelas > 1) {
                $totalParcelas = $dadoMM->parcelas;
            }
        }

        $valorParcela = round($repasse / $totalParcelas, 2);

        for ($i = 1; $i <= $totalParcelas; $i++) {
            $obs = $totalParcelas > 1
                ? "Repasse #{$venda->numero_pedido_canal} {$i}/{$totalParcelas}"
                : "Repasse #{$venda->numero_pedido_canal}";

            ContaReceber::create([
                'id_venda' => $venda->id_venda,
                'valor_parcela' => $valorParcela,
                'data_vencimento' => $venda->data_venda,
                'status' => 'pendente',
                'numero_parcela' => $i,
                'total_parcelas' => $totalParcelas,
                'forma_pagamento' => $canal?->nome_canal ?? 'Marketplace',
                'observacoes' => $obs,
                'lancamento_manual' => false,
            ]);
        }

        Log::info("ContaReceber gerada para venda #{$venda->id_venda} ({$venda->numero_pedido_canal}) - R$ " . number_format($repasse, 2) . " em {$totalParcelas}x");

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
        $contasExistentes = ContaReceber::where('id_venda', $venda->id_venda)
            ->where('forma_pagamento', 'not like', '%Subsídio%')
            ->where('lancamento_manual', false)
            ->get();

        $afiliado = (float) ($venda->comissao_afiliado ?? 0);
        $canal = $venda->canal;
        $isMagalu = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'magalu');
        $isShopee = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'shopee');
        $isML = $canal && (str_contains(strtolower($canal->nome_canal ?? ''), 'mercado') || str_starts_with($venda->numero_pedido_canal ?? '', '2000'));
        $isMM = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'madeira');

        if ($isMagalu) {
            $repasseBase = (float) $venda->valor_total_venda - (float) $venda->comissao;
            $repasse = round($repasseBase - $afiliado, 2);
        } elseif ($isML && (float) ($venda->ml_sale_fee ?? 0) > 0) {
            $mlSaleFee = (float) $venda->ml_sale_fee;
            $mlFreteCusto = (float) ($venda->ml_frete_custo ?? 0);
            $mlFreteReceita = (float) ($venda->ml_frete_receita ?? 0);
            $mlRebate = (float) ($venda->ml_valor_rebate ?? 0);
            if (in_array($venda->ml_tipo_frete, ['ME2', 'FULL'])) {
                $freteLiquido = $mlFreteCusto > 0 ? $mlFreteCusto - $mlFreteReceita : 0;
                $repasse = round((float) $venda->total_produtos - $mlSaleFee - $freteLiquido - $afiliado, 2);
            } else {
                $repasse = round((float) $venda->total_produtos + $mlFreteReceita - $mlSaleFee - $afiliado, 2);
            }
        } else {
            $repasseBase = (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao;
            $repasse = round($repasseBase - $afiliado, 2);
        }

        // Subsídio pix: para canais onde o marketplace repassa ao vendedor (exceto Shopee e Magalu)
        $subsidioPix = (float) ($venda->subsidio_pix ?? 0);
        if ($subsidioPix > 0 && !$isShopee && !$isMagalu) {
            $repasse += $subsidioPix;
        }

        // Determinar parcelas
        $totalParcelas = 1;
        if ($isMM) {
            $dadoMM = \App\Models\PlanilhaMmDado::where('numero_pedido', $venda->numero_pedido_canal)->first();
            if ($dadoMM && $dadoMM->parcelas > 1) {
                $totalParcelas = $dadoMM->parcelas;
            }
        }

        $valorParcela = round($repasse / $totalParcelas, 2);

        if ($contasExistentes->isNotEmpty()) {
            // Contas já recebidas ou em lote: NÃO alterar (valor travado)
            $travadas = $contasExistentes->filter(
                fn ($c) => $c->status === 'recebido' || $c->lote_recebimento_id || $c->fatura_recebimento_id
            );
            if ($travadas->count() === $contasExistentes->count()) {
                // Todas travadas, nada a fazer
                return false;
            }

            $pendentes = $contasExistentes->filter(
                fn ($c) => $c->status === 'pendente' && !$c->lote_recebimento_id && !$c->fatura_recebimento_id
            );

            // Se quantidade de parcelas mudou, deletar apenas as pendentes e recriar
            if ($contasExistentes->count() !== $totalParcelas) {
                $pendentes->each(fn ($conta) => $conta->delete());
                $existentesRestantes = ContaReceber::where('id_venda', $venda->id_venda)
                    ->where('forma_pagamento', 'not like', '%Subsídio%')
                    ->where('lancamento_manual', false)
                    ->count();
                for ($i = $existentesRestantes + 1; $i <= $totalParcelas; $i++) {
                    ContaReceber::create([
                        'id_venda' => $venda->id_venda,
                        'valor_parcela' => $valorParcela,
                        'data_vencimento' => $venda->data_venda,
                        'status' => 'pendente',
                        'numero_parcela' => $i,
                        'total_parcelas' => $totalParcelas,
                        'forma_pagamento' => $canal?->nome_canal ?? 'Marketplace',
                        'observacoes' => "Repasse #{$venda->numero_pedido_canal} {$i}/{$totalParcelas}",
                        'lancamento_manual' => false,
                    ]);
                }
            } else {
                // Só atualizar as pendentes (não travadas)
                foreach ($pendentes as $idx => $conta) {
                    $obs = $totalParcelas > 1
                        ? "Repasse #{$venda->numero_pedido_canal} " . ($idx + 1) . "/{$totalParcelas}"
                        : "Repasse #{$venda->numero_pedido_canal}";
                    $conta->update([
                        'valor_parcela' => $valorParcela,
                        'total_parcelas' => $totalParcelas,
                        'observacoes' => $obs,
                    ]);
                }
            }
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
