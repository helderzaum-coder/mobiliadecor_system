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
            ->whereHas('venda', fn ($q) => $q->where('comissao_afiliado', '>', 0));

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

            // Para vendas com afiliado, o repasse correto = valor atual - comissao_afiliado
            // (o valor original já estava correto, só faltava deduzir o afiliado)
            $afiliado = (float) ($venda->comissao_afiliado ?? 0);
            if ($afiliado <= 0) continue;

            $repasseCorreto = round((float) $conta->valor_parcela - $afiliado, 2);

            // Verificar se já foi deduzido (evitar deduzir duas vezes)
            // Se a diferença entre valor atual e (valor - afiliado) é exatamente o afiliado, precisa corrigir
            $valorAtual = round((float) $conta->valor_parcela, 2);

            if (abs($valorAtual - $repasseCorreto) > 0.01) {
                $divergentes++;
                $this->line(
                    "#{$venda->numero_pedido_canal} ({$canal?->nome_canal}): "
                    . "R$ " . number_format($valorAtual, 2, ',', '.')
                    . " → R$ " . number_format($repasseCorreto, 2, ',', '.')
                    . " (afiliado: -" . number_format($afiliado, 2, ',', '.') . ")"
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
