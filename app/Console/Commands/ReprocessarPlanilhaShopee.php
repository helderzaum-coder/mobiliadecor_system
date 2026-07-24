<?php

namespace App\Console\Commands;

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
