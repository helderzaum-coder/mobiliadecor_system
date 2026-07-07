<?php

namespace App\Console\Commands;

use App\Services\MercadoLivre\MercadoLivreClient;
use Illuminate\Console\Command;

class MlForcarMe1 extends Command
{
    protected $signature = 'ml:forcar-me1 {item_id} {--account=primary}';
    protected $description = 'Força shipping mode ME1 em um anúncio do Mercado Livre';

    public function handle(): int
    {
        $itemId = $this->argument('item_id');
        $account = $this->option('account');

        $client = new MercadoLivreClient($account);

        // 1. Buscar estado atual do item
        $this->info("Buscando item {$itemId} na conta {$account}...");
        $atual = $client->get("/items/{$itemId}", ['attributes' => 'id,title,shipping']);

        if (!$atual['success']) {
            $this->error("Erro ao buscar item: HTTP {$atual['http_code']}");
            $this->line(json_encode($atual['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 1;
        }

        $shipping = $atual['body']['shipping'] ?? [];
        $this->info("Título: " . ($atual['body']['title'] ?? '?'));
        $this->info("Shipping atual: mode={$shipping['mode']}, free_shipping=" . ($shipping['free_shipping'] ? 'true' : 'false'));
        $this->line("Logistic type: " . ($shipping['logistic_type'] ?? 'N/A'));

        // 2. Forçar ME1
        $this->info("\nAlterando para ME1...");
        $resultado = $client->put("/items/{$itemId}", [
            'shipping' => [
                'mode' => 'me1',
                'local_pick_up' => false,
                'free_shipping' => false,
            ],
        ]);

        if ($resultado['success']) {
            $novoShipping = $resultado['body']['shipping'] ?? [];
            $this->info("✅ Sucesso! Novo mode: " . ($novoShipping['mode'] ?? '?'));
            return 0;
        }

        $this->error("❌ Erro HTTP {$resultado['http_code']}");
        $this->line(json_encode($resultado['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Se deu erro, tentar entender
        $cause = $resultado['body']['cause'] ?? $resultado['body']['error'] ?? '';
        if (str_contains(json_encode($resultado['body']), 'shipping_mode')) {
            $this->warn("\nVerificando modos de envio aceitos pela categoria...");
            $catId = $atual['body']['category_id'] ?? null;
            if ($catId) {
                $modes = $client->get("/categories/{$catId}/shipping_modes");
                if ($modes['success']) {
                    $this->line(json_encode($modes['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return 1;
    }
}
