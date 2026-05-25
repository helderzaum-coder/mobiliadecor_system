<?php

namespace App\Console\Commands;

use App\Models\Cte;
use App\Models\Venda;
use App\Services\VendaRecalculoService;
use Illuminate\Console\Command;

class VincularCtesAutomatico extends Command
{
    protected $signature = 'ctes:vincular-auto {--dry-run : Apenas simula sem salvar}';
    protected $description = 'Vincula automaticamente CTEs pendentes às vendas pelo nome do destinatário ou chave NF-e';

    public function handle(): void
    {
        $ctes = Cte::where('utilizado', false)->get();
        $this->info("CTEs pendentes: {$ctes->count()}");

        $vinculados = 0;
        $naoEncontrados = 0;

        foreach ($ctes as $cte) {
            $venda = $this->buscarVenda($cte);

            if (!$venda) {
                $naoEncontrados++;
                $this->line("  ✗ CTE {$cte->numero_cte} — '{$cte->destinatario}' — sem venda encontrada");
                continue;
            }

            if ($this->option('dry-run')) {
                $this->info("  ✓ CTE {$cte->numero_cte} → Pedido #{$venda->numero_pedido_canal} (dry-run)");
                $vinculados++;
                continue;
            }

            $freteAtual = $venda->frete_pago ? (float) $venda->valor_frete_transportadora : 0;
            $novoFrete = ($cte->tipo ?? 'entrega') === 'entrega'
                ? $freteAtual + (float) $cte->valor_frete
                : $freteAtual;

            $venda->update([
                'valor_frete_transportadora' => round($novoFrete, 2),
                'nfe_chave_acesso' => $venda->nfe_chave_acesso ?: $cte->chave_nfe,
                'frete_pago' => true,
            ]);

            $cte->update([
                'utilizado' => true,
                'venda_id' => $venda->id_venda,
            ]);

            VendaRecalculoService::recalcularMargens($venda);

            $vinculados++;
            $this->info("  ✓ CTE {$cte->numero_cte} → Pedido #{$venda->numero_pedido_canal} — R$ " . number_format($cte->valor_frete, 2, ',', '.'));
        }

        $this->newLine();
        $this->info("Resultado: {$vinculados} vinculados | {$naoEncontrados} sem venda encontrada");
    }

    private function buscarVenda(Cte $cte): ?Venda
    {
        // Buscar pela chave NF-e (match exato, 1 NF-e = 1 pedido)
        if ($cte->chave_nfe) {
            // Chave completa
            $venda = Venda::where('nfe_chave_acesso', $cte->chave_nfe)->first();
            if ($venda) return $venda;

            // Chave parcial (CTE pode ter só parte da chave)
            $venda = Venda::where('nfe_chave_acesso', 'like', '%' . $cte->chave_nfe . '%')->first();
            if ($venda) return $venda;

            // Inverso: a chave do CTE contém a da venda
            $venda = Venda::whereRaw('? LIKE CONCAT(\'%\', nfe_chave_acesso, \'%\')', [$cte->chave_nfe])
                ->where('nfe_chave_acesso', '!=', '')
                ->whereNotNull('nfe_chave_acesso')
                ->first();
            if ($venda) return $venda;
        }

        return null;
    }
}
