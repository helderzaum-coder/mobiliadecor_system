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
        if (!$venda) {
            $this->error('Venda não encontrada.');
            return;
        }

        $canal = CanalVenda::find($venda->id_canal);
        $staging = PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();

        $this->info("=== VENDA {$venda->id_venda} ===");
        $this->line("Canal: {$canal?->nome_canal} (id: {$venda->id_canal})");
        $this->line("comissao_sobre_frete: " . ($canal?->comissao_sobre_frete ? 'SIM' : 'NÃO'));
        $this->line("planilha_processada: {$venda->planilha_processada}");
        $this->line("ml_sale_fee: {$venda->ml_sale_fee}");
        $this->line("comissao atual: {$venda->comissao}");
        $this->line("total_produtos: {$venda->total_produtos}");
        $this->line("valor_frete_cliente: {$venda->valor_frete_cliente}");
        $this->line("staging itens: " . json_encode($staging?->itens));

        $this->info("\n=== SIMULAÇÃO CÁLCULO ===");
        $itens = $staging?->itens ?? [['valor' => (float)$venda->total_produtos, 'quantidade' => 1, 'codigo' => 'PRODUTO']];
        $resultado = CalculoComissaoService::calcular(
            $venda->id_canal,
            $itens,
            $venda->ml_tipo_anuncio,
            $venda->ml_tipo_frete,
            (float)$venda->valor_frete_cliente
        );
        $this->line("Comissão calculada: {$resultado['comissao_total']}");
        foreach ($resultado['detalhes'] as $d) {
            $this->line("  Item: {$d['item']} | Valor: {$d['valor']} | Regra: {$d['regra']} | Comissão: {$d['comissao_total']}");
        }

        $this->info("\n=== REGRAS DO CANAL ===");
        foreach ($canal?->regrasComissao()->where('ativo', true)->get() ?? [] as $r) {
            $this->line("  [{$r->nome_regra}] {$r->percentual}% | faixa: {$r->faixa_valor_min} - {$r->faixa_valor_max} | cumulativa: " . ($r->cumulativa ? 'SIM' : 'NÃO'));
        }
    }
}
