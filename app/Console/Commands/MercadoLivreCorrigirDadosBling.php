<?php

namespace App\Console\Commands;

use App\Models\PedidoBlingStaging;
use App\Services\MercadoLivre\MercadoLivreCorrigirContatoBlingService;
use Illuminate\Console\Command;

class MercadoLivreCorrigirDadosBling extends Command
{
    protected $signature = 'ml:corrigir-dados-bling {order_id} {--account=primary} {--force}';
    protected $description = 'Busca telefone, complemento e endereço do ML e atualiza o contato no Bling';

    public function handle(): int
    {
        $orderId = $this->argument('order_id');
        $account = $this->option('account');
        $force   = $this->option('force');

        $staging = PedidoBlingStaging::where('numero_loja', $orderId)->first();

        if (!$staging) {
            $this->error("Pedido {$orderId} não encontrado no staging.");
            return 1;
        }

        if ($staging->bling_dados_corrigidos && !$force) {
            $this->warn("Pedido já foi corrigido. Use --force para forçar.");
            return 0;
        }

        $this->info("Corrigindo contato do pedido {$orderId} (Bling ID: {$staging->bling_id})...");

        // Resetar flag para o service poder marcar novamente
        if ($force) {
            $staging->update(['bling_dados_corrigidos' => false]);
        }

        $ok = MercadoLivreCorrigirContatoBlingService::corrigir($staging, $account);

        if ($ok) {
            $this->info("✓ Contato atualizado com sucesso.");
        } else {
            $this->error("✗ Falha ao atualizar contato. Verifique os logs.");
            return 1;
        }

        return 0;
    }
}
