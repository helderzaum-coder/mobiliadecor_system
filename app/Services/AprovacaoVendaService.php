<?php

namespace App\Services;

use App\Models\CanalVenda;
use App\Models\PedidoBlingStaging;
use App\Models\Venda;
use Illuminate\Support\Facades\Log;

class AprovacaoVendaService
{
    /**
     * Aprova um pedido do staging e cria o registro na tabela vendas
     * com cálculo de margens.
     */
    public static function aprovar(PedidoBlingStaging $staging): Venda
    {
        $canal = CanalVenda::where('nome_canal', $staging->canal)->first();
        $cnpjId = config("bling.accounts.{$staging->bling_account}.cnpj_id");

        $totalProdutos = (float) $staging->total_produtos;
        $frete = (float) $staging->frete;
        $custoFrete = (float) $staging->custo_frete;
        $comissao = (float) $staging->comissao_calculada;
        $subsidioPix = (float) $staging->subsidio_pix;
        $valorImposto = (float) $staging->valor_imposto;
        $totalPedido = (float) $staging->total_pedido;

        // Custo total dos produtos (soma dos custos dos itens)
        $custoProdutos = 0;
        foreach ($staging->itens ?? [] as $item) {
            $custoProdutos += ((float) ($item['custo'] ?? 0)) * ((int) ($item['quantidade'] ?? 1));
        }

        // Margem Frete = Frete cobrado - Custo frete
        $margemFrete = $frete - $custoFrete;

        // Margem Produto = Subtotal - Custo Produtos - Comissão - Imposto
        $margemProduto = $totalProdutos - $custoProdutos - $comissao - $valorImposto;

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
            'numero_nota_fiscal' => $staging->nota_fiscal,
            'valor_total_venda' => $totalPedido,
            'total_produtos' => $totalProdutos,
            'custo_produtos' => round($custoProdutos, 2),
            'valor_frete_cliente' => $frete,
            'valor_frete_transportadora' => $custoFrete,
            'comissao' => $comissao,
            'subsidio_pix' => $subsidioPix,
            'base_imposto' => (float) $staging->base_imposto,
            'percentual_imposto' => (float) $staging->percentual_imposto,
            'valor_imposto' => $valorImposto,
            'id_canal' => $canal?->id_canal,
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
        ]);

        $staging->update(['status' => 'aprovado']);

        Log::info("Pedido {$staging->numero_pedido} aprovado -> Venda #{$venda->id_venda}");

        return $venda;
    }
}
