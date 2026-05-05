<?php

namespace App\Services;

use App\Models\CanalVenda;
use App\Models\PedidoBlingStaging;
use App\Models\PlanilhaMlDado;
use App\Models\PlanilhaShopeeDado;
use App\Models\Venda;
use App\Services\Bling\BlingClient;
use App\Services\ContaReceberService;
use Illuminate\Support\Facades\Log;

class VendaRecalculoService
{
    /**
     * Busca NF-e no Bling e atualiza a venda.
     */
    public static function buscarNfe(Venda $venda): array
    {
        $staging = PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
        if (!$staging) {
            return ['success' => false, 'msg' => 'Staging não encontrado.'];
        }

        $found = \App\Services\Bling\BlingImportService::buscarNfePorPedido($staging);
        if (!$found) {
            return ['success' => false, 'msg' => 'NF-e não encontrada no Bling.'];
        }

        $venda->update([
            'numero_nota_fiscal' => $staging->nfe_numero,
            'nfe_chave_acesso' => $staging->nfe_chave_acesso,
            'nfe_valor' => $staging->nfe_valor,
            'base_imposto' => $staging->base_imposto,
            'percentual_imposto' => $staging->percentual_imposto,
            'valor_imposto' => $staging->valor_imposto,
        ]);

        self::recalcularMargens($venda);

        return ['success' => true, 'msg' => "NF-e {$staging->nfe_numero} vinculada."];
    }

    /**
     * Busca CT-e no banco e aplica na venda.
     */
    public static function buscarCte(Venda $venda): array
    {
        return CteService::aplicarCteNaVenda($venda);
    }

    /**
     * Aplica dados financeiros do ML via API (ou planilha como fallback).
     */
    public static function aplicarPlanilhaML(Venda $venda): array
    {
        $numeroPedido = $venda->numero_pedido_canal;

        // Tentar buscar via API do ML
        try {
            $mlAccount = $venda->bling_account ?? 'primary';
            $mlService = new \App\Services\MercadoLivre\MercadoLivreOrderService($mlAccount);
            $dados = $mlService->buscarDadosPedido((string) $numeroPedido);

            if ($dados && $dados['net_received_amount'] > 0) {
                return self::aplicarDadosMLNaVenda($venda, $dados);
            }
        } catch (\Exception $e) {
            Log::warning("ML API fallback para planilha, pedido {$numeroPedido}: " . $e->getMessage());
        }

        // Fallback: planilha
        $dado = PlanilhaMlDado::where('numero_venda', $numeroPedido)->first();

        if (!$dado) {
            return ['success' => false, 'msg' => "Dados ML não encontrados para pedido {$numeroPedido} (API e planilha)."];
        }

        $saleFee = abs((float) $dado->tarifa_venda);
        $freteCusto = abs((float) $dado->tarifas_envio);
        $freteReceita = (float) $dado->receita_envio;
        $rebate = (float) $dado->rebate;
        $temRebate = (bool) $dado->tem_rebate;

        $venda->update([
            'ml_sale_fee' => $saleFee,
            'ml_frete_custo' => $freteCusto,
            'ml_frete_receita' => $freteReceita,
            'ml_valor_rebate' => $rebate,
            'ml_tem_rebate' => $temRebate,
            'planilha_processada' => true,
        ]);

        $tipoFrete = $venda->ml_tipo_frete;
        if ($tipoFrete === 'ME2' || $tipoFrete === 'FULL') {
            $taxaFreteML = $freteCusto > 0 ? ($freteCusto - $freteReceita) : 0;
            $comissao = $saleFee + $taxaFreteML;
            $venda->update([
                'comissao' => $comissao,
                'valor_frete_cliente' => 0,
                'valor_frete_transportadora' => 0,
            ]);
        } else {
            $venda->update([
                'comissao' => $saleFee,
                'valor_frete_cliente' => $freteReceita,
                'valor_frete_transportadora' => $freteCusto,
            ]);
        }

        self::recalcularMargens($venda);

        return ['success' => true, 'msg' => "Planilha ML aplicada (fallback). Comissão: R$ " . number_format($venda->comissao, 2, ',', '.')];
    }

    /**
     * Aplica dados financeiros obtidos da API do ML na venda.
     */
    private static function aplicarDadosMLNaVenda(Venda $venda, array $dados): array
    {
        $saleFee = (float) $dados['sale_fee'];
        $freteCusto = (float) $dados['frete_ml_custo'];
        $freteReceita = (float) $dados['frete_ml_receita'];
        $rebate = (float) $dados['valor_rebate'];
        $temRebate = (bool) $dados['tem_rebate'];
        $tipoFrete = $dados['tipo_frete'] ?? $venda->ml_tipo_frete;

        $venda->update([
            'ml_sale_fee' => $saleFee,
            'ml_frete_custo' => $freteCusto,
            'ml_frete_receita' => $freteReceita,
            'ml_valor_rebate' => $rebate,
            'ml_tem_rebate' => $temRebate,
            'ml_tipo_anuncio' => $dados['tipo_anuncio'] ?? $venda->ml_tipo_anuncio,
            'ml_tipo_frete' => $tipoFrete,
            'ml_order_id' => $dados['order_id'] ?? $venda->ml_order_id,
            'ml_shipping_id' => $dados['shipping_id'] ?? $venda->ml_shipping_id,
            'planilha_processada' => true,
        ]);

        if ($tipoFrete === 'ME2' || $tipoFrete === 'FULL') {
            // custo líquido = list_cost - cost
            $freteLiquido = $freteCusto > 0 ? round($freteCusto - $freteReceita, 2) : 0;
            $venda->update([
                'comissao' => $saleFee + $freteLiquido,
                'valor_frete_cliente' => 0,
                'valor_frete_transportadora' => 0,
            ]);
        } else {
            $venda->update([
                'comissao' => $saleFee,
                'valor_frete_cliente' => $freteReceita,
                'valor_frete_transportadora' => $freteCusto,
            ]);
        }

        self::recalcularMargens($venda);

        return ['success' => true, 'msg' => "Dados ML aplicados via API. Comissão: R$ " . number_format($venda->comissao, 2, ',', '.') . ($temRebate ? " | Rebate: R$ " . number_format($rebate, 2, ',', '.') : '')];
    }

    /**
     * Aplica dados da planilha Shopee já importada.
     */
    public static function aplicarPlanilhaShopee(Venda $venda): array
    {
        $numeroPedido = $venda->numero_pedido_canal;
        $dado = PlanilhaShopeeDado::where('numero_pedido', $numeroPedido)->first();

        if (!$dado) {
            return ['success' => false, 'msg' => "Planilha Shopee não encontrada para pedido {$numeroPedido}."];
        }

        // Usar dados_originais que tem os valores corretos do novo mapeamento
        $originais = $dado->dados_originais ?? [];
        $comissao = abs((float) ($originais['comissao'] ?? $dado->taxa_comissao));
        $subsidioPix = abs((float) ($originais['subsidio_pix'] ?? 0));
        $frete = (float) ($originais['frete'] ?? $dado->taxa_envio);
        $totalProdutos = (float) ($originais['total_produtos'] ?? 0);
        $totalPedido = (float) ($originais['total_pedido'] ?? 0);

        $updateData = [
            'comissao' => $comissao,
            'subsidio_pix' => $subsidioPix, // Exibir na dashboard (não soma no lucro pois já descontado do subtotal)
            'planilha_processada' => true,
        ];

        // Atualizar valores de produto e frete se vieram da planilha
        if ($totalProdutos > 0) {
            $updateData['total_produtos'] = $totalProdutos;
        }
        if ($totalPedido > 0) {
            $updateData['valor_total_venda'] = $totalPedido;
        }
        if ($frete >= 0) {
            $updateData['valor_frete_cliente'] = $frete;
        }

        $venda->update($updateData);

        self::recalcularMargens($venda);

        return ['success' => true, 'msg' => "Planilha Shopee aplicada. Comissão: R$ " . number_format($comissao, 2, ',', '.') . " | Subsídio: R$ " . number_format($subsidioPix, 2, ',', '.')];
    }

    /**
     * Aplica dados da planilha Madeira Madeira já importada.
     */
    public static function aplicarPlanilhaMM(Venda $venda): array
    {
        $numeroPedido = $venda->numero_pedido_canal;
        $dado = \App\Models\PlanilhaMmDado::where('numero_pedido', $numeroPedido)->first();

        if (!$dado) {
            return ['success' => false, 'msg' => "Planilha MM não encontrada para pedido {$numeroPedido}."];
        }

        $comissao = (float) $dado->comissao;

        $venda->update([
            'comissao' => $comissao,
            'planilha_processada' => true,
        ]);

        self::recalcularMargens($venda);

        return ['success' => true, 'msg' => "Planilha MM aplicada. Comissão: R$ " . number_format($comissao, 2, ',', '.')];
    }

    /**
     * Recalcula margens da venda com os dados atuais.
     */
    public static function recalcularMargens(Venda $venda): void
    {
        $venda->refresh();

        $canal = $venda->id_canal ? CanalVenda::find($venda->id_canal) : null;

        // Se comissão está zerada e canal tem regras, recalcular
        if ((float) $venda->comissao == 0 && $canal) {
            $staging = PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
            $itens = $staging?->itens ?? [];
            if (!empty($itens)) {
                $comissaoData = CalculoComissaoService::calcular($canal->id_canal, $itens, null, null, (float) $venda->valor_frete_cliente);
                if ($comissaoData['comissao_total'] > 0) {
                    $venda->update([
                        'comissao' => $comissaoData['comissao_total'],
                        'subsidio_pix' => $comissaoData['subsidio_pix_total'],
                    ]);
                    $venda->refresh();
                }
            }
        }

        $totalProdutos = (float) $venda->total_produtos;
        $frete = (float) $venda->valor_frete_cliente;
        $custoFrete = (float) $venda->valor_frete_transportadora;
        $comissao = (float) $venda->comissao;
        $subsidioPix = (float) $venda->subsidio_pix;

        // TikTokShop: marketplace paga o frete (igual ML ME2/FULL)
        $isTiktok = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'tiktok');
        if ($isTiktok && ($frete > 0 || $custoFrete > 0)) {
            $frete = 0;
            $custoFrete = 0;
            $venda->update([
                'valor_frete_cliente' => 0,
                'valor_frete_transportadora' => 0,
                'frete_pago' => true,
            ]);
        }
        $valorImposto = (float) $venda->valor_imposto;
        $totalPedido = (float) $venda->valor_total_venda;
        $custoProdutos = (float) $venda->custo_produtos;
        $percentualImposto = (float) $venda->percentual_imposto;
        $valorRebate = (float) ($venda->ml_valor_rebate ?? 0);

        // Se sale_fee veio da API, o rebate já está descontado — não somar de novo
        if ((float) ($venda->ml_sale_fee ?? 0) > 0) {
            $valorRebate = 0;
        }

        $comissaoSobreFrete = (bool) ($canal->comissao_sobre_frete ?? false);
        $impostoSobreFrete = (bool) ($canal->imposto_sobre_frete ?? false);

        // Comissão sobre frete
        $comissaoFrete = 0;
        if ($comissaoSobreFrete && $frete > 0 && $canal) {
            $regra = $canal->regrasComissao()->where('ativo', true)->first();
            if ($regra) {
                $comissaoFrete = round($frete * (float) $regra->percentual / 100, 2);
            }
        }

        // Imposto sobre frete
        $impostoFrete = 0;
        if ($impostoSobreFrete && $frete > 0 && $percentualImposto > 0) {
            $impostoFrete = round($frete * $percentualImposto / 100, 2);
        }

        $impostoProduto = $valorImposto - $impostoFrete;
        $margemFrete = $frete - $custoFrete - $comissaoFrete - $impostoFrete;
        $comissaoProduto = $comissao - $comissaoFrete;
        $margemProduto = $totalProdutos - $custoProdutos - $comissaoProduto - $impostoProduto + $valorRebate;

        // Subsídio pix / descontos:
        // - Shopee: já descontado do subtotal, não somar
        // - Magalu: subsídio Magalu, não somar no lucro (já está na base de comissão)
        // - Outros: somar no lucro
        $isShopee = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'shopee');
        $isMagalu = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'magalu');
        $subsidioNoLucro = ($isShopee || $isMagalu) ? 0 : $subsidioPix;

        $margemVendaTotal = $margemProduto + $margemFrete + $subsidioNoLucro;
        $margemContribuicao = $totalPedido > 0
            ? round(($margemVendaTotal / $totalPedido) * 100, 2)
            : 0;

        $venda->update([
            'margem_frete' => round($margemFrete, 2),
            'margem_produto' => round($margemProduto, 2),
            'margem_venda_total' => round($margemVendaTotal, 2),
            'margem_contribuicao' => round($margemContribuicao, 2),
        ]);

        // Gerar conta a receber se venda ficou completa
        ContaReceberService::gerarSeCompleta($venda->fresh());
    }
}
