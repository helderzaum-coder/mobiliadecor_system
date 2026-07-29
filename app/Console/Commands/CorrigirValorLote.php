<?php

namespace App\Console\Commands;

use App\Models\ContaReceber;
use App\Models\FaturaRecebimento;
use App\Models\LoteRecebimento;
use Illuminate\Console\Command;

class CorrigirValorLote extends Command
{
    protected $signature = 'corrigir:valor-lote {lote_id}';
    protected $description = 'Recalcula e corrige o valor_total de um lote com base nas contas reais + descontos/entradas da fatura';

    public function handle(): void
    {
        $loteId = (int) $this->argument('lote_id');

        $lote = LoteRecebimento::find($loteId);
        if (!$lote) {
            $this->error("Lote #{$loteId} não encontrado.");
            return;
        }

        $somaContas = (float) ContaReceber::where('lote_recebimento_id', $loteId)->sum('valor_parcela');

        $fatura = FaturaRecebimento::where('lote_recebimento_id', $loteId)->first();
        $totalDescontos = $fatura ? collect($fatura->descontos)->sum('valor') : 0;
        $totalEntradas  = $fatura ? collect($fatura->entradas_avulsas)->sum('valor') : 0;

        $valorCorreto = round($somaContas + $totalEntradas - $totalDescontos, 2);
        $valorAtual   = (float) $lote->valor_total;

        $this->info("Lote #{$loteId}: {$lote->descricao}");
        $this->line("  Valor atual:   R$ " . number_format($valorAtual, 2, ',', '.'));
        $this->line("  Soma contas:   R$ " . number_format($somaContas, 2, ',', '.'));
        $this->line("  Descontos:     R$ " . number_format($totalDescontos, 2, ',', '.'));
        $this->line("  Entradas:      R$ " . number_format($totalEntradas, 2, ',', '.'));
        $this->line("  Valor correto: R$ " . number_format($valorCorreto, 2, ',', '.'));

        if (round($valorAtual, 2) === $valorCorreto) {
            $this->info("Valor já está correto. Nada a fazer.");
            return;
        }

        if (!$this->confirm("Corrigir de R$ " . number_format($valorAtual, 2, ',', '.') . " para R$ " . number_format($valorCorreto, 2, ',', '.') . "?")) {
            $this->warn("Cancelado.");
            return;
        }

        $lote->update(['valor_total' => $valorCorreto]);
        $this->info("Lote #{$loteId} corrigido para R$ " . number_format($valorCorreto, 2, ',', '.') . " ✓");
    }
}
