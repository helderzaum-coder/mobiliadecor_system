<?php

namespace App\Console\Commands;

use App\Models\ContaReceber;
use App\Models\FaturaRecebimento;
use App\Models\LoteRecebimento;
use Illuminate\Console\Command;

class DiagnosticarLote extends Command
{
    protected $signature = 'diagnosticar:lote {lote_id}';
    protected $description = 'Diagnóstico completo de um lote de recebimento';

    public function handle(): void
    {
        $loteId = (int) $this->argument('lote_id');

        $lote = LoteRecebimento::find($loteId);
        if (!$lote) {
            $this->error("Lote #{$loteId} não encontrado.");
            return;
        }

        $this->info("=== LOTE #{$loteId} ===");
        $this->line("Descrição:    {$lote->descricao}");
        $this->line("Data:         " . $lote->data_recebimento->format('d/m/Y'));
        $this->line("Valor total:  R$ " . number_format($lote->valor_total, 2, ',', '.'));
        $this->line("Qtd contas:   {$lote->quantidade_contas}");

        // Fatura vinculada ao lote
        $fatura = FaturaRecebimento::where('lote_recebimento_id', $loteId)->first();
        if ($fatura) {
            $this->info("\n=== FATURA VINCULADA ===");
            $this->line("Fatura ID:    #{$fatura->id}");
            $this->line("Status:       {$fatura->status}");
            $this->line("Valor fatura: R$ " . number_format($fatura->valor_total, 2, ',', '.'));
            $this->line("Descontos:    R$ " . number_format(collect($fatura->descontos)->sum('valor'), 2, ',', '.'));
            $this->line("Entradas:     R$ " . number_format(collect($fatura->entradas_avulsas)->sum('valor'), 2, ',', '.'));
        } else {
            $this->warn("\nNenhuma fatura vinculada a este lote.");
        }

        // Contas vinculadas ao lote
        $contas = ContaReceber::with('venda')
            ->where('lote_recebimento_id', $loteId)
            ->get();

        $this->info("\n=== CONTAS NO LOTE ({$contas->count()}) ===");
        $somaContas = 0;
        foreach ($contas as $c) {
            $pedido = $c->venda?->numero_pedido_canal ?? 'avulsa';
            $somaContas += (float) $c->valor_parcela;
            $faturaInfo = $c->fatura_recebimento_id ? " | fatura_id={$c->fatura_recebimento_id}" : '';
            $this->line(sprintf(
                "  id=%d | pedido=%-20s | status=%-10s | R$ %8s%s",
                $c->id_conta_receber,
                $pedido,
                $c->status,
                number_format($c->valor_parcela, 2, ',', '.'),
                $faturaInfo
            ));
        }
        $this->line("  SOMA CONTAS: R$ " . number_format($somaContas, 2, ',', '.'));

        // Diferença entre valor do lote e soma das contas
        $diff = round($lote->valor_total - $somaContas, 2);
        if ($diff != 0) {
            $this->warn("\n  DIFERENÇA lote vs soma contas: R$ " . number_format($diff, 2, ',', '.'));
            if ($fatura) {
                $totalDescontos = collect($fatura->descontos)->sum('valor');
                $totalEntradas  = collect($fatura->entradas_avulsas)->sum('valor');
                $esperado = round($somaContas + $totalEntradas - $totalDescontos, 2);
                $this->line("  Valor esperado (contas + entradas - descontos): R$ " . number_format($esperado, 2, ',', '.'));
            }
        } else {
            $this->info("\n  Soma das contas bate com o valor do lote. ✓");
        }

        // Contas que têm fatura_recebimento_id mas NÃO estão no lote
        if ($fatura) {
            $contasFatura = ContaReceber::with('venda')
                ->where('fatura_recebimento_id', $fatura->id)
                ->where(fn ($q) => $q->whereNull('lote_recebimento_id')->orWhere('lote_recebimento_id', '!=', $loteId))
                ->get();

            if ($contasFatura->isNotEmpty()) {
                $this->warn("\n=== CONTAS NA FATURA MAS FORA DO LOTE ({$contasFatura->count()}) ===");
                foreach ($contasFatura as $c) {
                    $pedido = $c->venda?->numero_pedido_canal ?? 'avulsa';
                    $this->line(sprintf(
                        "  id=%d | pedido=%-20s | status=%-10s | lote_id=%s | R$ %s",
                        $c->id_conta_receber,
                        $pedido,
                        $c->status,
                        $c->lote_recebimento_id ?? 'NULL',
                        number_format($c->valor_parcela, 2, ',', '.')
                    ));
                }
            }
        }

        // Contas com status != recebido dentro do lote
        $naoRecebidas = $contas->where('status', '!=', 'recebido');
        if ($naoRecebidas->isNotEmpty()) {
            $this->warn("\n=== CONTAS NO LOTE COM STATUS != recebido ===");
            foreach ($naoRecebidas as $c) {
                $pedido = $c->venda?->numero_pedido_canal ?? 'avulsa';
                $this->line("  id={$c->id_conta_receber} | pedido={$pedido} | status={$c->status}");
            }
        }
    }
}
