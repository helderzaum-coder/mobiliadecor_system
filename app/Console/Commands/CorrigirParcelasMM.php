<?php

namespace App\Console\Commands;

use App\Models\ContaReceber;
use App\Models\PlanilhaMmDado;
use App\Models\Venda;
use App\Services\ContaReceberService;
use Illuminate\Console\Command;

class CorrigirParcelasMM extends Command
{
    protected $signature = 'vendas:corrigir-parcelas-mm {--dry-run : Simular sem alterar}';

    protected $description = 'Corrige vendas Madeira Madeira que têm parcelas > 1 mas só 1 ContaReceber';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Buscar planilha_mm_dados com parcelas > 1
        $dadosMM = PlanilhaMmDado::where('parcelas', '>', 1)->get();

        if ($dadosMM->isEmpty()) {
            $this->info('Nenhum pedido MM com parcelas > 1 encontrado na planilha.');
            return 0;
        }

        $this->info("Encontrados {$dadosMM->count()} pedidos MM com múltiplas parcelas.");

        $corrigidos = 0;

        foreach ($dadosMM as $dado) {
            $venda = Venda::where('numero_pedido_canal', $dado->numero_pedido)->first();
            if (!$venda) continue;

            // Contar contas a receber existentes (não manuais, não subsídio)
            $contasExistentes = ContaReceber::where('id_venda', $venda->id_venda)
                ->where('forma_pagamento', 'not like', '%Subsídio%')
                ->where('lancamento_manual', false)
                ->get();

            // Já está correto?
            if ($contasExistentes->count() === $dado->parcelas) continue;

            // Tem alguma já em lote? Não mexer
            $emLote = $contasExistentes->where('lote_recebimento_id', '!=', null)->count();
            if ($emLote > 0) {
                $this->warn("  #{$dado->numero_pedido}: tem parcela(s) em lote, pulando.");
                continue;
            }

            $this->line(
                "  #{$dado->numero_pedido}: {$contasExistentes->count()} conta(s) → {$dado->parcelas} parcelas"
                . " (R$ " . number_format($contasExistentes->sum('valor_parcela'), 2, ',', '.') . ")"
            );

            if (!$dryRun) {
                // Deletar as existentes
                $contasExistentes->each->delete();

                // Regenerar via service (vai criar com parcelas corretas)
                ContaReceberService::gerarSeCompleta($venda->fresh());
                $corrigidos++;
            }
        }

        if ($dryRun) {
            $this->warn("Simulação concluída. Use sem --dry-run para aplicar.");
        } else {
            $this->info("{$corrigidos} venda(s) corrigida(s) com parcelas.");
        }

        return 0;
    }
}
