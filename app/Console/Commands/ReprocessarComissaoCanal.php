<?php

namespace App\Console\Commands;

use App\Models\CanalVenda;
use App\Models\PedidoBlingStaging;
use App\Models\Venda;
use App\Services\CalculoComissaoService;
use App\Services\VendaRecalculoService;
use Illuminate\Console\Command;

class ReprocessarComissaoCanal extends Command
{
    protected $signature = 'vendas:reprocessar-comissao
                            {canal? : Nome do canal (ex: Amazon, Madeira Madeira)}
                            {--todos : Reprocessar todos os canais}';

    protected $description = 'Recalcula comissão e margens das vendas de um canal';

    public function handle(): int
    {
        $nomeCanal = $this->argument('canal');
        $todos = $this->option('todos');

        if (!$nomeCanal && !$todos) {
            $canais = CanalVenda::where('ativo', true)->orderBy('nome_canal')->pluck('nome_canal')->toArray();
            $nomeCanal = $this->choice('Qual canal reprocessar?', $canais);
        }

        $query = Venda::query();

        if (!$todos) {
            $canal = CanalVenda::where('nome_canal', $nomeCanal)->first();
            if (!$canal) {
                $canal = CanalVenda::get()->first(
                    fn ($c) => str_replace(' ', '', strtolower($c->nome_canal)) === str_replace(' ', '', strtolower($nomeCanal))
                );
            }
            if (!$canal) {
                $this->error("Canal '{$nomeCanal}' não encontrado.");
                return 1;
            }
            $query->where('id_canal', $canal->id_canal);
            $this->info("Reprocessando vendas do canal: {$canal->nome_canal}");
        } else {
            $this->info("Reprocessando vendas de TODOS os canais");
        }

        $vendas = $query->get();
        $this->info("Total: {$vendas->count()} vendas");

        $bar = $this->output->createProgressBar($vendas->count());
        $recalculados = 0;

        foreach ($vendas as $venda) {
            $canalVenda = $venda->id_canal ? CanalVenda::find($venda->id_canal) : null;
            if (!$canalVenda) {
                $bar->advance();
                continue;
            }

            $staging = PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
            $itens = $staging?->itens ?? [];

            if (!empty($itens)) {
                $comissaoData = CalculoComissaoService::calcular($canalVenda->id_canal, $itens);
                $venda->update([
                    'comissao' => $comissaoData['comissao_total'],
                    'subsidio_pix' => $comissaoData['subsidio_pix_total'],
                ]);
            }

            VendaRecalculoService::recalcularMargens($venda);
            $recalculados++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$recalculados} venda(s) reprocessada(s).");
        return 0;
    }
}
