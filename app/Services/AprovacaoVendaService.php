<?php

namespace App\Services;

use App\Models\CanalVenda;
use App\Models\PedidoBlingStaging;
use App\Models\Venda;
use Illuminate\Support\Facades\Log;

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  ATENÇÃO: CÓDIGO ESTÁVEL E FUNCIONAL — NÃO SOBRESCREVER           ║
 * ║                                                                    ║
 * ║  Serviço de aprovação de pedidos: staging → vendas.                ║
 * ║  Calcula margens (produto, frete, total) com regras específicas:   ║
 * ║  - ML ME2/FULL: frete=0, taxa frete ML entra como comissão         ║
 * ║  - ML ME1: frete = receita (comprador), custoFrete = custo (ML)    ║
 * ║  - ML sale_fee da API já tem rebate descontado (valorRebate = 0)   ║
 * ║  - Shopee Xpress: frete = 0, sem custo frete                      ║
 * ║  - Comissão sobre frete e imposto sobre frete por canal            ║
 * ║                                                                    ║
 * ║  Referência funcional: commit de 23/03/2026                        ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */
class AprovacaoVendaService
{
    /**
     * Aprova um pedido do staging e cria o registro na tabela vendas
     * com cálculo de margens.
     *
     * ⚠️ NÃO ALTERAR SEM TESTAR COM PEDIDOS REAIS (ML ME1, ME2, Shopee, Direto).
     * Lógica ML:
     *  - ME2/FULL: frete=0, comissão = sale_fee + (frete_custo - frete_receita)
     *  - ME1: frete = ml_frete_receita, custoFrete = ml_frete_custo, comissão = sale_fee
     *  - sale_fee da API já tem rebate descontado → valorRebate = 0
     */
    public static function aprovar(PedidoBlingStaging $staging): Venda
    {
        // Garantir que itens esteja carregado (pode não vir no select da listagem)
        if (!array_key_exists('itens', $staging->getAttributes())) {
            $staging->itens = PedidoBlingStaging::where('id', $staging->id)->value('itens');
        }

        $canal = CanalVenda::where('nome_canal', $staging->canal)->first();

        // Fallback: busca flexível removendo espaços (ex: "Madeiramadeira" → "Madeira Madeira")
        if (!$canal) {
            $canal = CanalVenda::get()->first(
                fn ($c) => str_replace(' ', '', strtolower($c->nome_canal)) === str_replace(' ', '', strtolower($staging->canal))
            );
        }
        $cnpjId = config("bling.accounts.{$staging->bling_account}.cnpj_id");

        $totalProdutos = (float) $staging->total_produtos;
        $frete = (float) $staging->frete;
        $custoFrete = (float) $staging->custo_frete;
        $comissao = (float) $staging->comissao_calculada;
        $subsidioPix = (float) $staging->subsidio_pix;
        $valorImposto = (float) $staging->valor_imposto;
        $totalPedido = (float) $staging->total_pedido;
        $valorRebate = (float) ($staging->ml_valor_rebate ?? 0);
        $percentualImposto = (float) $staging->percentual_imposto;
        $tipoNota = $canal->tipo_nota ?? 'cheia';
        $comissaoSobreFrete = (bool) ($canal->comissao_sobre_frete ?? false);

        // Custo total dos produtos (soma dos custos dos itens)
        $custoProdutos = 0;
        foreach ($staging->itens ?? [] as $item) {
            $custoProdutos += ((float) ($item['custo'] ?? 0)) * ((int) ($item['quantidade'] ?? 1));
        }

        // Para pedidos ML, ajustar comissão e frete conforme tipo
        $isML = str_contains(strtolower($staging->canal ?? ''), 'mercado')
            || str_starts_with($staging->numero_loja ?? '', '2000');
        $mlSaleFee = (float) ($staging->ml_sale_fee ?? 0);
        $mlFreteCusto = (float) ($staging->ml_frete_custo ?? 0);
        $mlFreteReceita = (float) ($staging->ml_frete_receita ?? 0);

        if ($isML) {
            $tipoFrete = $staging->ml_tipo_frete ?? null;

            if ($tipoFrete === 'ME2' || $tipoFrete === 'FULL') {
                // ME2/FULL: vendedor não lida com frete
                // custo líquido = list_cost - cost (o que o ML reteve)
                $freteLiquido = $mlFreteCusto > 0 ? round($mlFreteCusto - $mlFreteReceita, 2) : 0;
                if ($mlSaleFee > 0) {
                    $comissao = $mlSaleFee + $freteLiquido;
                    $valorRebate = 0;
                }
                $frete = 0;
                $custoFrete = 0;
            } else {
                // ME1: frete é cobrado do cliente e repassado ao vendedor
                if ($mlFreteReceita > 0) {
                    $frete = $mlFreteReceita; // valor pago pelo comprador
                }
                if ($mlFreteCusto > 0) {
                    $custoFrete = $mlFreteCusto; // custo cobrado pelo ML
                }
                // Se temos sale_fee real da API, usar como comissão
                if ($mlSaleFee > 0) {
                    $comissao = $mlSaleFee;
                    $valorRebate = 0; // já descontado no sale_fee
                }
            }
        }

        // Comissão sobre frete (se canal cobra)
        $comissaoFrete = 0;
        if ($comissaoSobreFrete && $frete > 0 && $canal) {
            $regra = $canal->regrasComissao()->where('ativo', true)->first();
            if ($regra) {
                $comissaoFrete = round($frete * (float) $regra->percentual / 100, 2);
            }
        }

        // Imposto proporcional sobre frete (se canal tem flag imposto_sobre_frete)
        $impostoFrete = 0;
        $impostoSobreFrete = (bool) ($canal->imposto_sobre_frete ?? false);
        if ($impostoSobreFrete && $frete > 0 && $percentualImposto > 0) {
            $impostoFrete = round($frete * $percentualImposto / 100, 2);
        }

        // Imposto sobre produto = imposto total - imposto frete
        $impostoProduto = $valorImposto - $impostoFrete;

        // Margem Frete = Frete cobrado - Custo frete - Comissão frete - Imposto frete
        $margemFrete = $frete - $custoFrete - $comissaoFrete - $impostoFrete;

        // Comissão sobre produtos = comissão total - comissão frete
        $comissaoProduto = $comissao - $comissaoFrete;

        // Margem Produto = Subtotal - Custo Produtos - Comissão Produto - Imposto Produto + Rebate
        $margemProduto = $totalProdutos - $custoProdutos - $comissaoProduto - $impostoProduto + $valorRebate;

        // Margem Venda Total (Lucro Final) = Margem Produto + Margem Frete + Subsídio Pix
        $margemVendaTotal = $margemProduto + $margemFrete + $subsidioPix;

        // Margem Contribuição % = Lucro Final / Total Pedido × 100
        $margemContribuicao = $totalPedido > 0
            ? round(($margemVendaTotal / $totalPedido) * 100, 2)
            : 0;

        $venda = Venda::create([
            'bling_id' => $staging->bling_id,
            'bling_account' => $staging->bling_account,
            'numero_pedido_canal' => $staging->numero_loja ?? $staging->numero_pedido,
            'numero_nota_fiscal' => $staging->nota_fiscal ?? '',
            'nfe_chave_acesso' => $staging->nfe_chave_acesso ?? null,
            'nfe_valor' => $staging->nfe_valor ?? null,
            'valor_total_venda' => $totalPedido,
            'total_produtos' => $totalProdutos,
            'custo_produtos' => round($custoProdutos, 2),
            'valor_frete_cliente' => $frete,
            'valor_frete_transportadora' => $custoFrete,
            'frete_cotado' => $custoFrete, // Guardar cotação original
            'comissao' => $comissao,
            'subsidio_pix' => $subsidioPix,
            'base_imposto' => (float) $staging->base_imposto,
            'percentual_imposto' => (float) $staging->percentual_imposto,
            'valor_imposto' => $valorImposto,
            'id_canal' => $canal?->id_canal,
            'canal_nome' => $canal?->nome_canal ?? $staging->canal,
            'id_cnpj' => $cnpjId,
            'data_venda' => $staging->data_pedido,
            'cliente_nome' => $staging->cliente_nome,
            'cliente_documento' => $staging->cliente_documento,
            'frete_pago' => false,
            'observacoes' => $staging->observacoes,
            'bling_situacao_id' => $staging->situacao_id,
            'margem_frete' => round($margemFrete, 2),
            'margem_produto' => round($margemProduto, 2),
            'margem_venda_total' => round($margemVendaTotal, 2),
            'margem_contribuicao' => round($margemContribuicao, 2),
            'ml_tipo_anuncio' => $staging->ml_tipo_anuncio,
            'ml_tipo_frete' => $staging->ml_tipo_frete,
            'ml_tem_rebate' => $staging->ml_tem_rebate ?? false,
            'ml_valor_rebate' => $staging->ml_valor_rebate ?? 0,
            'ml_sale_fee' => $staging->ml_sale_fee ?? 0,
            'ml_frete_custo' => $staging->ml_frete_custo ?? 0,
            'ml_frete_receita' => $staging->ml_frete_receita ?? 0,
            'ml_order_id' => $staging->ml_order_id,
            'ml_shipping_id' => $staging->ml_shipping_id,
            'planilha_processada' => (float) ($staging->ml_sale_fee ?? 0) > 0,
        ]);

        $staging->update(['status' => 'aprovado']);

        Log::info("Pedido {$staging->numero_pedido} aprovado -> Venda #{$venda->id_venda}");

        return $venda;
    }
}
