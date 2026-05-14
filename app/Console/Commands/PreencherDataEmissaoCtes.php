<?php

namespace App\Console\Commands;

use App\Models\Cte;
use Illuminate\Console\Command;

class PreencherDataEmissaoCtes extends Command
{
    protected $signature = 'ctes:preencher-data-emissao';
    protected $description = 'Preenche data_emissao dos CT-es antigos extraindo da chave_cte (posições 2-5 = AAMM)';

    public function handle(): void
    {
        $ctes = Cte::whereNull('data_emissao')->whereNotNull('chave_cte')->where('chave_cte', '!=', '')->get();

        $atualizados = 0;
        foreach ($ctes as $cte) {
            // Chave CT-e: UF(2) + AAMM(4) + CNPJ(14) + ...
            $chave = $cte->chave_cte;
            if (strlen($chave) < 6) continue;

            $aa = substr($chave, 2, 2);
            $mm = substr($chave, 4, 2);

            if (!is_numeric($aa) || !is_numeric($mm) || (int)$mm < 1 || (int)$mm > 12) continue;

            $ano = 2000 + (int) $aa;
            $data = "{$ano}-{$mm}-01";

            $cte->update(['data_emissao' => $data]);
            $atualizados++;
        }

        $this->info("Atualizados: {$atualizados} CT-es");
    }
}
