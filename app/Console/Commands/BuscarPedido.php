<?php

namespace App\Console\Commands;

use App\Models\PedidoBlingStaging;
use App\Models\Venda;
use Illuminate\Console\Command;

class BuscarPedido extends Command
{
    protected $signature = 'pedido:buscar {numero}';
    protected $description = 'Busca um pedido por número nas vendas e staging';

    public function handle(): void
    {
        $numero = $this->argument('numero');

        $this->info("Buscando: {$numero}");
        $this->newLine();

        // Buscar em vendas
        $vendas = Venda::where('numero_pedido_canal', 'like', "%{$numero}%")->get();
        if ($vendas->isNotEmpty()) {
            $this->info("=== VENDAS ({$vendas->count()}) ===");
            foreach ($vendas as $v) {
                $this->line("  ID: {$v->id_venda} | Pedido: {$v->numero_pedido_canal} | Canal: {$v->canal_nome} | Data: {$v->data_venda?->format('d/m/Y')} | Total: R$ " . number_format($v->valor_total_venda, 2, ',', '.') . " | Cancelada: " . ($v->cancelada ? 'SIM' : 'não'));
            }
        } else {
            $this->warn("Nenhuma venda encontrada.");
        }

        $this->newLine();

        // Buscar em staging
        $stagings = PedidoBlingStaging::where('numero_pedido', 'like', "%{$numero}%")
            ->orWhere('numero_loja', 'like', "%{$numero}%")
            ->get();

        if ($stagings->isNotEmpty()) {
            $this->info("=== STAGING ({$stagings->count()}) ===");
            foreach ($stagings as $s) {
                $this->line("  ID: {$s->id} | Bling: {$s->bling_id} | Pedido: {$s->numero_pedido} | Loja: {$s->numero_loja} | Canal: {$s->canal} | Status: {$s->status} | Data: {$s->data_pedido?->format('d/m/Y')}");
            }
        } else {
            $this->warn("Nenhum staging encontrado.");
        }
    }
}
