<?php

namespace App\Console\Commands;

use App\Jobs\ImportarPedidosBlingJob;
use Illuminate\Console\Command;

class ImportarPedidosBlingAutomatico extends Command
{
    protected $signature = 'bling:importar-automatico';
    protected $description = 'Importa pedidos do Bling das últimas 24h para ambas as contas';

    public function handle(): void
    {
        // Busca 2 dias atrás para cobrir pedidos criados no final do dia
        $dataInicio = now()->subDays(2)->toDateString();
        $dataFim = now()->toDateString();

        $contas = ['primary', 'secondary'];

        foreach ($contas as $conta) {
            ImportarPedidosBlingJob::dispatch($conta, $dataInicio, $dataFim);
            $this->info("Job de importação disparado para conta: {$conta} ({$dataInicio} a {$dataFim})");
        }
    }
}
