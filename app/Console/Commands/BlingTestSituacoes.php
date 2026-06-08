<?php

namespace App\Console\Commands;

use App\Services\Bling\BlingClient;
use Illuminate\Console\Command;

class BlingTestSituacoes extends Command
{
    protected $signature = 'bling:test-situacoes';
    protected $description = 'Testa endpoints de situações do Bling';

    public function handle(): void
    {
        $client = new BlingClient('primary');

        // Módulo Vendas = 98310 (descoberto via GET /situacoes/modulos)
        $this->info('=== GET /situacoes/modulos/98310 (Pedidos de Venda) ===');
        $res = $client->get('/situacoes/modulos/98310');
        $this->line(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
