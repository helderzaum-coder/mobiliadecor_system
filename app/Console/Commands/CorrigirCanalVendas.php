<?php

namespace App\Console\Commands;

use App\Models\CanalVenda;
use App\Models\Venda;
use Illuminate\Console\Command;

class CorrigirCanalVendas extends Command
{
    protected $signature = 'vendas:corrigir-canal';
    protected $description = 'Corrige vendas com id_canal nulo ou canal_nome não vinculado';

    public function handle(): int
    {
        $canais = CanalVenda::all();
        $corrigidos = 0;

        // Vendas sem id_canal mas com canal_nome
        $vendas = Venda::whereNull('id_canal')
            ->whereNotNull('canal_nome')
            ->where('canal_nome', '!=', '')
            ->get();

        foreach ($vendas as $venda) {
            $canal = $canais->first(
                fn ($c) => str_replace(' ', '', strtolower($c->nome_canal)) === str_replace(' ', '', strtolower($venda->canal_nome))
            );
            if ($canal) {
                $venda->update([
                    'id_canal' => $canal->id_canal,
                    'canal_nome' => $canal->nome_canal,
                ]);
                $corrigidos++;
                $this->line("Venda #{$venda->id_venda} ({$venda->canal_nome}) → {$canal->nome_canal}");
            }
        }

        // Vendas sem canal_nome mas com staging
        $semNome = Venda::whereNull('canal_nome')->orWhere('canal_nome', '')->get();
        foreach ($semNome as $venda) {
            $staging = \App\Models\PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
            if (!$staging) continue;

            $canal = $canais->first(
                fn ($c) => str_replace(' ', '', strtolower($c->nome_canal)) === str_replace(' ', '', strtolower($staging->canal))
            );
            if ($canal) {
                $venda->update([
                    'id_canal' => $canal->id_canal,
                    'canal_nome' => $canal->nome_canal,
                ]);
                $corrigidos++;
                $this->line("Venda #{$venda->id_venda} (staging: {$staging->canal}) → {$canal->nome_canal}");
            }
        }

        $this->info("{$corrigidos} venda(s) corrigida(s).");
        return 0;
    }
}
