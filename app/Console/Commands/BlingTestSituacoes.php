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

        // Testar endpoint de módulos
        $this->info('=== GET /situacoes/modulos ===');
        $res = $client->get('/situacoes/modulos');
        $this->line(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Testar módulos comuns (1 a 15)
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15] as $modulo) {
            $this->info("=== GET /situacoes/modulos/{$modulo} ===");
            $r = $client->get("/situacoes/modulos/{$modulo}");
            if ($r['success'] && !empty($r['body']['data'])) {
                $this->line("Módulo {$modulo}: " . json_encode($r['body']['data'], JSON_UNESCAPED_UNICODE));
            } else {
                $this->line("Módulo {$modulo}: HTTP {$r['http_code']} - sem dados");
            }
        }

        // Testar endpoint alternativo
        $this->info('=== GET /situacoes/transicoes ===');
        $res2 = $client->get('/situacoes/transicoes');
        $this->line(json_encode($res2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
