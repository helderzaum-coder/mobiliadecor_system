<?php

namespace App\Console\Commands;

use App\Models\PlanilhaShopeeDado;
use App\Models\PlanilhaShopeeDado;
use App\Models\Venda;
use App\Services\VendaRecalculoService;
use Illuminate\Console\Command;

class ReprocessarPlanilhaShopee extends Command
{
    protected $signature = 'shopee:reprocessar-planilha {mes} {ano}';
    protected $description = 'Reprocessa dados da planilha Shopee para vendas de um mês/ano, corrigindo cupom_plataforma duplicado';

    public function handle(): void
    {
        $mes = (int) $this->argument('mes');
        $ano = (int) $this->argument('ano');

        $vendas = Venda::whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%'))
            ->where('planilha_processada', true)
            ->whereMonth('data_venda', $mes)
            ->whereYear('data_venda', $ano)
            ->get();

        $this->info("Encontradas {$vendas->count()} vendas Shopee em {$mes}/{$ano}");

        $corrigidos = 0;
        $semPlanilha = 0;

        foreach ($vendas as $venda) {
            // Corrigir dados_originais no PlanilhaShopeeDado antes de reaplicar
            $dado = PlanilhaShopeeDado::where('numero_pedido', $venda->numero_pedido_canal)->first();
            if (!$dado && $venda->bling_id) {
                $staging = \App\Models\PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
                if ($staging?->numero_loja) {
                    $dado = PlanilhaShopeeDado::where('numero_pedido', $staging->numero_loja)->first();
                }
            }

            if ($dado && $dado->dados_originais) {
                $originais = $dado->dados_originais;
                $precosBruto = (float) ($originais['itens'][0]['valor'] ?? 0);
                // Se tem mais de um item, somar todos
                if (!empty($originais['itens'])) {
                    $precosBruto = array_sum(array_column($originais['itens'], 'valor'));
                }
                $cupomShopee = (float) ($originais['cupom_shopee'] ?? 0);
                $subsidioPix = (float) ($originais['subsidio_pix'] ?? 0);
                $frete = (float) ($originais['frete'] ?? 0);

                // Recalcular com formula correta: total_produtos = bruto - cupom (sem pix)
                $originais['total_produtos'] = round($precosBruto - $cupomShopee, 2);
                $originais['total_pedido'] = round($originais['total_produtos'] + $frete, 2);
                $dado->update(['dados_originais' => $originais]);
            }

            $resultado = VendaRecalculoService::aplicarPlanilhaShopee($venda);
            if ($resultado['success']) {
                $corrigidos++;
            } else {
                $semPlanilha++;
            }
        }

        $this->info("Corrigidos: {$corrigidos} | Sem planilha: {$semPlanilha}");
    }
}
