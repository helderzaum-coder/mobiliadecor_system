<?php

namespace App\Console\Commands;

use App\Models\Venda;
use App\Services\ContaReceberService;
use Illuminate\Console\Command;

class GerarContasReceberML extends Command
{
    protected $signature = 'ml:gerar-contas-receber {--dry-run : Simular sem criar} {--desde= : Data início no formato Y-m-d (ex: 2026-07-19)}';

    protected $description = 'Gera contas a receber para vendas ML que ainda não têm (custo preenchido)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $desde = $this->option('desde');

        $query = Venda::with('canal')
            ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%ercado%'))
            ->where('custo_produtos', '>', 0)
            ->where('cancelada', false)
            ->whereDoesntHave('contasReceber')
            ->orderBy('data_venda');

        if ($desde) {
            $query->where('data_venda', '>=', $desde);
        }

        $vendas = $query->get();

        $this->info("Vendas ML sem conta a receber e com custo: {$vendas->count()}");

        if ($vendas->isEmpty()) {
            $this->info('Nenhuma venda para processar.');
            return 0;
        }

        $geradas = 0;
        $puladas = 0;

        foreach ($vendas as $venda) {
            $repasse = $this->calcularRepasse($venda);

            $this->line(
                "#{$venda->numero_pedido_canal} | {$venda->data_venda->format('d/m/Y')} | "
                . "R$ " . number_format($repasse, 2, ',', '.')
                . ($repasse <= 0 ? ' ⚠️  repasse zero/negativo' : '')
            );

            if ($repasse <= 0) {
                $puladas++;
                continue;
            }

            if (!$dryRun) {
                ContaReceberService::gerarSeCompleta($venda);
                $geradas++;
            } else {
                $geradas++;
            }
        }

        if ($dryRun) {
            $this->warn("--dry-run: {$geradas} conta(s) seriam geradas, {$puladas} puladas (repasse zero).");
        } else {
            $this->info("{$geradas} conta(s) gerada(s), {$puladas} puladas.");
        }

        return 0;
    }

    private function calcularRepasse(Venda $venda): float
    {
        $mlSaleFee = (float) ($venda->ml_sale_fee ?? 0);
        $afiliado = (float) ($venda->comissao_afiliado ?? 0);

        if ($mlSaleFee > 0) {
            $mlFreteCusto = (float) ($venda->ml_frete_custo ?? 0);
            $mlFreteReceita = (float) ($venda->ml_frete_receita ?? 0);

            if (in_array($venda->ml_tipo_frete, ['ME2', 'FULL'])) {
                $freteLiquido = $mlFreteCusto > 0 ? $mlFreteCusto - $mlFreteReceita : 0;
                return (float) $venda->total_produtos - $mlSaleFee - $freteLiquido - $afiliado;
            }

            return (float) $venda->total_produtos + $mlFreteReceita - $mlSaleFee - $afiliado;
        }

        return (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao - $afiliado;
    }
}
