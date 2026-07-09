<?php

namespace App\Console\Commands;

use App\Models\Cte;
use Illuminate\Console\Command;

class PreencherDadosCtes extends Command
{
    protected $signature = 'ctes:preencher-dados';
    protected $description = 'Preenche numero_nfe a partir da chave_nfe nos CT-es existentes';

    public function handle(): void
    {
        $ctes = Cte::whereNull('numero_nfe')
            ->whereNotNull('chave_nfe')
            ->where('chave_nfe', '!=', '')
            ->get();

        $count = 0;
        foreach ($ctes as $cte) {
            $updates = [];

            // Extrair número da NF-e da chave (posições 25-33, 0-indexed)
            if (strlen($cte->chave_nfe) === 44) {
                $updates['numero_nfe'] = ltrim(substr($cte->chave_nfe, 25, 9), '0');
            }

            if (!empty($updates)) {
                $cte->update($updates);
                $count++;
            }
        }

        $this->info("{$count} CT-e(s) atualizados.");
    }
}
