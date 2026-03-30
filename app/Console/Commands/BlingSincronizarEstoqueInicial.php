<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BlingSincronizarEstoqueInicial extends Command
{
    protected $signature = 'bling:sync-estoque-inicial
                            {origem=primary : Conta de origem (primary|secondary)}
                            {--limit=0 : Limitar quantidade de SKUs (0 = todos)}
                            {--dry-run : Simula sem atualizar}';

    protected $description = 'Sincroniza estoque completo de uma conta Bling para a outra (DESATIVADO)';

    public function handle(): int
    {
        $this->warn('Sincronização de estoque desativada.');
        return 0;
    }
}
