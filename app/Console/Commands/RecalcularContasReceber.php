<?php

namespace App\Console\Commands;

use App\Models\ContaReceber;
use App\Models\Venda;
use App\Services\ContaReceberService;
use Illuminate\Console\Command;

class RecalcularContasReceber extends Command
{
    protected $signature = 'vendas:recalcular-contas
                            {--mes= : Mês no formato Y-m (ex: 2026-04)}
                            {--dry-run : Simular sem alterar}';

    protected $description = 'Recalcula valor das contas a receber pendentes que estão divergentes';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $mes = $this->option('mes');

        $query = ContaReceber::where('status', 'pendente')
            ->whereHas('venda');

        if ($mes) {
            $query->whereHas('venda', fn ($q) => $q
                ->whereYear('data_venda', substr($mes, 0, 4))
                ->whereMonth('data_venda', substr($mes, 5, 2))
            );
        }

        $contas = $query->with('venda.canal')->get();
        $this->info("Verificando {$contas->count()} contas a receber pendentes...");

        $divergentes = 0;
        $corrigidos = 0;

        foreach ($contas as $conta) {
            $venda = $conta->venda;
            if (!$venda) continue;

            $canal = $venda->canal;
            $isMagalu = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'magalu');

            if ($isMagalu) {
                $repasseCorreto = (float) $venda->valor_total_venda - (float) $venda->comissao - (float) ($venda->comissao_afiliado ?? 0) + (float) $venda->subsidio_pix;
            } else {
                $repasseCorreto = (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao - (float) ($venda->comissao_afiliado ?? 0);
            }
            $repasseCorreto = round($repasseCorreto, 2);
            $valorAtual = round((float) $conta->valor_parcela, 2);

            if (abs($valorAtual - $repasseCorreto) > 0.01) {
                $divergentes++;
                $diff = $repasseCorreto - $valorAtual;
                $this->line(
                    "#{$venda->numero_pedido_canal} ({$canal?->nome_canal}): "
                    . "R$ " . number_format($valorAtual, 2, ',', '.')
                    . " → R$ " . number_format($repasseCorreto, 2, ',', '.')
                    . " (" . ($diff > 0 ? '+' : '') . number_format($diff, 2, ',', '.') . ")"
                );

                if (!$dryRun) {
                    $conta->update(['valor_parcela' => $repasseCorreto]);
                    $corrigidos++;
                }
            }
        }

        if ($dryRun) {
            $this->warn("{$divergentes} conta(s) com valor divergente. Use sem --dry-run para corrigir.");
        } else {
            $this->info("{$corrigidos} conta(s) corrigida(s).");
        }

        return 0;
    }
}
