<?php

namespace App\Console\Commands;

use App\Models\ContaReceber;
use Illuminate\Console\Command;

class CorrigirSubsidioMagalu extends Command
{
    protected $signature = 'fix:subsidio-magalu';
    protected $description = 'Corrige contas de subsídio Magalu que foram sobrescritas pelo recálculo';

    public function handle(): void
    {
        $contas = ContaReceber::with('venda')
            ->where('forma_pagamento', 'Magalu - Subsídio')
            ->whereHas('venda', fn ($q) => $q->where('subsidio_magalu', '>', 0))
            ->get();

        $corrigidos = 0;

        foreach ($contas as $conta) {
            $valorCorreto = (float) $conta->venda->subsidio_magalu;
            $valorAtual = (float) $conta->valor_parcela;

            if (abs($valorAtual - $valorCorreto) > 0.01) {
                $this->line("Pedido {$conta->venda->numero_pedido_canal}: R$ " . number_format($valorAtual, 2, ',', '.') . " → R$ " . number_format($valorCorreto, 2, ',', '.'));
                $conta->update(['valor_parcela' => $valorCorreto]);
                $corrigidos++;
            }
        }

        $this->info("{$corrigidos} conta(s) de subsídio corrigida(s).");
    }
}
