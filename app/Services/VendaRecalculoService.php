<?php

namespace App\Services;

use App\Models\CanalVenda;
use App\Models\PedidoBlingStaging;
use App\Models\PlanilhaMlDado;
use App\Models\PlanilhaShopeeDado;
use App\Models\Venda;
use App\Services\Bling\BlingClient;
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
     * Busca CT-e e atualiza custo frete na venda.
     */
    public static function buscarCte(Venda $venda): array
    {
        $staging = PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
        if (!$staging) {
            return ['success' => false, 'msg' => 'Staging não encontrado.'];
        }

        // Garantir que staging tem chave NF-e
        if (empty($staging->nfe_chave_acesso) && !empty($venda->nfe_chave_acesso)) {
            $staging->update(['nfe_chave_acesso' => $venda->nfe_chave_acesso]);
        }

        $result = CteService::processarCte($staging);
        if (!$result['success']) {
            return $result;
        }

        $venda->update([
            'valor_frete_transportadora' => $staging->custo_frete,
            'frete_pago' => true,
        ]);

        self::recalcularMargens($venda);

        return $result;
    }

    /**
     * Aplica dados da planilha ML já importada.
     */
    public static function aplicarPlanilhaML(Venda $venda): array
    {
        $numeroPedido = $venda->numero_pedido_canal;
        $dado = PlanilhaMlDado::where('numero_venda', $numeroPedido)->first();

        if (!$dado) {
            return ['success' => false, 'msg' => "Planilha ML não encontrada para pedido {$numeroPedido}."];
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

        // Recalcular comissão ML
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

        return ['success' => true, 'msg' => "Planilha ML aplicada. Comissão: R$ " . number_format($venda->comissao, 2, ',', '.')];
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
            'subsidio_pix' => $subsidioPix,
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
     * Recalcula margens da venda com os dados atuais.
     */
    public static function recalcularMargens(Venda $venda): void
    {
        $venda->refresh();

        $canal = $venda->id_canal ? CanalVenda::find($venda->id_canal) : null;
        $totalProdutos = (float) $venda->total_produtos;
        $frete = (float) $venda->valor_frete_cliente;
        $custoFrete = (float) $venda->valor_frete_transportadora;
        $comissao = (float) $venda->comissao;
        $subsidioPix = (float) $venda->subsidio_pix;
        $valorImposto = (float) $venda->valor_imposto;
        $totalPedido = (float) $venda->valor_total_venda;
        $custoProdutos = (float) $venda->custo_produtos;
        $percentualImposto = (float) $venda->percentual_imposto;
        $valorRebate = (float) ($venda->ml_valor_rebate ?? 0);

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
        $margemVendaTotal = $margemProduto + $margemFrete + $subsidioPix;
        $margemContribuicao = $totalPedido > 0
            ? round(($margemVendaTotal / $totalPedido) * 100, 2)
            : 0;

        $venda->update([
            'margem_frete' => round($margemFrete, 2),
            'margem_produto' => round($margemProduto, 2),
            'margem_venda_total' => round($margemVendaTotal, 2),
            'margem_contribuicao' => round($margemContribuicao, 2),
        ]);
    }
}
