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
        $staging = PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
        $cupom = (float) ($venda->cupom_shopee ?? 0);

        $this->info("=== VENDA {$venda->id_venda} ===");
        $this->line("comissao atual: {$venda->comissao}");
        $this->line("cupom_shopee: {$cupom}");
        $this->line("total_produtos: {$venda->total_produtos}");
        $this->line("valor_frete_cliente: {$venda->valor_frete_cliente}");

        $itens = $staging?->itens ?? [];
        $this->line("staging itens: " . json_encode($itens));

        if (empty($itens)) {
            $this->error('Sem itens no staging!');
            return;
        }

        $totalItens = array_sum(array_map(fn ($i) => (float)($i['valor'] ?? 0) * (int)($i['quantidade'] ?? 1), $itens));
        $this->info("\n=== SEM CUPOM ===");
        $r1 = CalculoComissaoService::calcular($venda->id_canal, $itens, null, null, (float)$venda->valor_frete_cliente);
        $this->line("Comissão: {$r1['comissao_total']}");
        foreach ($r1['detalhes'] as $d) {
            $this->line("  [{$d['regra']}] base={$d['valor']} => {$d['comissao_total']}");
        }

        if ($cupom > 0) {
            $this->info("\n=== COM CUPOM R$ {$cupom} ===");
            $fator = $totalItens > 0 ? (($totalItens - $cupom) / $totalItens) : 1;
            $this->line("totalItens={$totalItens} | fator={$fator}");
            $itensDesc = array_map(fn ($i) => array_merge($i, ['valor' => round((float)($i['valor'] ?? 0) * $fator, 4)]), $itens);
            $this->line("itens com desconto: " . json_encode($itensDesc));
            $r2 = CalculoComissaoService::calcular($venda->id_canal, $itensDesc, null, null, (float)$venda->valor_frete_cliente);
            $this->line("Comissão: {$r2['comissao_total']}");
            foreach ($r2['detalhes'] as $d) {
                $this->line("  [{$d['regra']}] base={$d['valor']} => {$d['comissao_total']}");
            }

            $this->info("\n=== ESPERADO (manual) ===");
            $this->line("Base = {$totalItens} - {$cupom} = " . ($totalItens - $cupom));
            $this->line("Regras do canal:");
            foreach ($canal->regrasComissao()->where('ativo', true)->get() as $r) {
                $this->line("  [{$r->nome_regra}] {$r->percentual}% + fixo:{$r->valor_fixo} | faixa:{$r->faixa_valor_min}-{$r->faixa_valor_max} | cumulativa:" . ($r->cumulativa ? 'SIM' : 'NÃO'));
            }
        }
    }
}
