<?php

namespace App\Console\Commands;

use App\Models\PedidoBlingStaging;
use Illuminate\Console\Command;

class PreencherTransportadoraStaging extends Command
{
    protected $signature = 'staging:preencher-transportadora';
    protected $description = 'Preenche campo transportadora a partir de dados_originais para registros existentes';

    public function handle(): int
    {
        $updated = 0;

        PedidoBlingStaging::whereNull('transportadora')
            ->whereNotNull('dados_originais')
            ->chunkById(500, function ($stagings) use (&$updated) {
                foreach ($stagings as $staging) {
                    $nome = $staging->dados_originais['transporte']['contato']['nome'] ?? null;
                    if ($nome) {
                        $staging->update(['transportadora' => $nome]);
                        $updated++;
                    }
                }
            });

        $this->info("Transportadora preenchida em {$updated} registros.");
        return 0;
    }
}
