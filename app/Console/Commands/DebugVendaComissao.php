<?php

namespace App\Console\Commands;

use App\Models\CanalVenda;
use App\Models\PedidoBlingStaging;
use App\Models\Venda;
use App\Services\CalculoComissaoService;
use Illuminate\Console\Command;

class DebugVendaComissao extends Command
{
    protected $signature = 'debug:comissao {id_venda}';
    protected $description = 'Debug do cálculo de comissão de uma venda';

    public function handle()
    {
        $venda = Venda::find($this->argument('id_venda'));
        if (!$venda) { $this->error('Venda não encontrada.'); return; }

        $canal = CanalVenda::find($venda->id_canal);
        $cupom = (float) ($venda->cupom_shopee ?? 0);

        $this->info("=== VENDA {$venda->id_venda} ===");
        $this->line("comissao atual: {$venda->comissao}");
        $this->line("cupom_shopee: {$cupom}");
        $this->line("total_produtos: {$venda->total_produtos}");
        $this->line("valor_frete_cliente: {$venda->valor_frete_cliente}");

        $this->info("\n=== REGRAS DO CANAL ===");
        foreach ($canal->regrasComissao()->where('ativo', true)->get() as $r) {
            $this->line("  [{$r->nome_regra}] {$r->percentual}% + fixo:{$r->valor_fixo} | faixa:{$r->faixa_valor_min}-{$r->faixa_valor_max} | cumulativa:" . ($r->cumulativa ? 'SIM' : 'NÃO'));
        }

        // Simula exatamente o que recalcularMargens faz com cupom
        if ($cupom > 0) {
            $baseComissao = (float) $venda->total_produtos - $cupom;
            $this->info("\n=== COM CUPOM (lógica recalcularMargens) ===");
            $this->line("base = total_produtos({$venda->total_produtos}) - cupom({$cupom}) = {$baseComissao}");
            $itensBase = [['valor' => $baseComissao, 'quantidade' => 1, 'codigo' => 'SHOPEE']];
            $r2 = CalculoComissaoService::calcular($venda->id_canal, $itensBase, null, null, (float) $venda->valor_frete_cliente);
            $this->line("Comissão calculada: {$r2['comissao_total']}");
            foreach ($r2['detalhes'] as $d) {
                $this->line("  [{$d['regra']}] base={$d['valor']} => {$d['comissao_total']}");
            }
        }

        // Simula sem cupom usando total_produtos
        $this->info("\n=== SEM CUPOM (usando total_produtos da venda) ===");
        $itens = [['valor' => (float) $venda->total_produtos, 'quantidade' => 1, 'codigo' => 'SHOPEE']];
        $r1 = CalculoComissaoService::calcular($venda->id_canal, $itens, null, null, (float) $venda->valor_frete_cliente);
        $this->line("Comissão calculada: {$r1['comissao_total']}");
        foreach ($r1['detalhes'] as $d) {
            $this->line("  [{$d['regra']}] base={$d['valor']} => {$d['comissao_total']}");
        }
    }
}
