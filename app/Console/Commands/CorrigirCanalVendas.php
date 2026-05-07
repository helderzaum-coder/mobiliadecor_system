<?php

namespace App\Console\Commands;

use App\Models\CanalVenda;
use App\Models\Venda;
use Illuminate\Console\Command;

class CorrigirCanalVendas extends Command
{
    protected $signature = 'vendas:corrigir-canal';
    protected $description = 'Corrige vendas com id_canal nulo ou canal_nome não vinculado e recalcula margens';

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
                \App\Services\VendaRecalculoService::recalcularMargens($venda);
                $corrigidos++;
                $this->line("Venda #{$venda->id_venda} ({$venda->canal_nome}) → {$canal->nome_canal} [recalculada]");
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
                \App\Services\VendaRecalculoService::recalcularMargens($venda);
                $corrigidos++;
                $this->line("Venda #{$venda->id_venda} (staging: {$staging->canal}) → {$canal->nome_canal} [recalculada]");
            }
        }

        // Vendas com comissão zerada que têm canal vinculado
        $semComissao = Venda::whereNotNull('id_canal')
            ->where(fn ($q) => $q->where('comissao', 0)->orWhereNull('comissao'))
            ->get();
        foreach ($semComissao as $venda) {
            \App\Services\VendaRecalculoService::recalcularMargens($venda);
            if ((float) $venda->fresh()->comissao > 0) {
                $corrigidos++;
                $this->line("Venda #{$venda->id_venda} comissão recalculada: R$ " . number_format($venda->fresh()->comissao, 2, ',', '.'));
            }
        }

        // Vendas ML com sale_fee mas planilha_processada=false
        $mlSemFlag = Venda::where('planilha_processada', false)
            ->where('ml_sale_fee', '>', 0)
            ->get();
        foreach ($mlSemFlag as $venda) {
            $venda->update(['planilha_processada' => true]);
            $corrigidos++;
            $this->line("Venda #{$venda->id_venda} ML: marcada planilha_processada=true");
        }

        // Vendas com canal_nome inválido (contém número de pedido)
        $invalidos = Venda::where(function ($q) {
            $q->where('canal_nome', 'like', '%- id cp:%')
              ->orWhere('canal_nome', 'like', '%2000%')
              ->orWhereRaw('LENGTH(canal_nome) > 30');
        })->get();
        foreach ($invalidos as $venda) {
            $staging = \App\Models\PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
            $canalNome = $staging->canal ?? null;
            $canal = $canalNome ? $canais->first(
                fn ($c) => str_replace(' ', '', strtolower($c->nome_canal)) === str_replace(' ', '', strtolower($canalNome))
            ) : null;

            if ($canal) {
                $venda->update(['id_canal' => $canal->id_canal, 'canal_nome' => $canal->nome_canal]);
                $corrigidos++;
                $this->line("Venda #{$venda->id_venda} canal_nome corrigido: {$venda->canal_nome} → {$canal->nome_canal}");
            }
        }

        $this->info("{$corrigidos} venda(s) corrigida(s).");
        return 0;
    }
}
