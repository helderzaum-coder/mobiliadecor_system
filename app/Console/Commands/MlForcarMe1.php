<?php

namespace App\Console\Commands;

use App\Services\MercadoLivre\MercadoLivreClient;
use Illuminate\Console\Command;

class MlForcarMe1 extends Command
{
    protected $signature = 'ml:forcar-me1 {item_id} {--account=primary} {--debug}';
    protected $description = 'Força shipping mode ME1 em um anúncio do Mercado Livre';

    public function handle(): int
    {
        $itemId = $this->argument('item_id');
        $account = $this->option('account'); 
        $debug = $this->option('debug');

        $client = new MercadoLivreClient($account);

        // 1. Buscar estado atual do item
        $this->info("Buscando item {$itemId} na conta {$account}...");
        $atual = $client->get("/items/{$itemId}");

        if (!$atual['success']) {
            $this->error("Erro ao buscar item: HTTP {$atual['http_code']}");
            $this->line(json_encode($atual['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 1;
        }

        $item = $atual['body'];
        $shipping = $item['shipping'] ?? [];
        $this->info("Título: " . ($item['title'] ?? '?'));
        $this->info("Shipping atual: mode={$shipping['mode']}, free_shipping=" . ($shipping['free_shipping'] ? 'true' : 'false'));
        $this->line("Logistic type: " . ($shipping['logistic_type'] ?? 'N/A'));
        $this->line("Free methods: " . json_encode($shipping['free_methods'] ?? []));

        if ($debug) {
            $this->line("\nShipping completo:");
            $this->line(json_encode($shipping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // 2. Verificar shipping_options (custos de frete configurados)
        $this->info("\nVerificando opções de frete do item...");
        $options = $client->get("/items/{$itemId}/shipping_options", ['zip_code' => '01001000']);
        if ($options['success']) {
            $opts = $options['body']['options'] ?? [];
            if (empty($opts)) {
                $this->warn("⚠️  Nenhuma opção de frete configurada! O comprador vê 'frete indisponível'.");
                $this->warn("   O item precisa de free_shipping=true ou shipping costs cadastrados.");
            } else {
                $this->info("Opções de frete encontradas: " . count($opts));
                foreach ($opts as $opt) {
                    $this->line("  - {$opt['name']}: R$ " . number_format(($opt['cost'] ?? 0), 2, ',', '.') . " ({$opt['estimated_delivery_time']['shipping'] ?? '?'} dias)");
                }
            }
        } else {
            $this->warn("Não foi possível verificar opções de frete: HTTP {$options['http_code']}");
            if ($debug) {
                $this->line(json_encode($options['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        // 3. Forçar ME1 com free_shipping true (frete grátis por conta do vendedor)
        $this->info("\nAlterando para ME1 com free_shipping=true...");
        $resultado = $client->put("/items/{$itemId}", [
            'shipping' => [
                'mode' => 'me1',
                'local_pick_up' => false,
                'free_shipping' => true,
                'free_methods' => [
                    ['id' => 73328, 'rule' => ['free_mode' => 'country', 'value' => null]],
                ],
            ],
        ]);

        if ($resultado['success']) {
            $novoShipping = $resultado['body']['shipping'] ?? [];
            $this->info("✅ Sucesso! mode={$novoShipping['mode']}, free_shipping=" . ($novoShipping['free_shipping'] ? 'true' : 'false'));
            $this->line("Free methods: " . json_encode($novoShipping['free_methods'] ?? []));
            return 0;
        }

        $this->error("❌ Erro HTTP {$resultado['http_code']}");
        $this->line(json_encode($resultado['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Fallback: tentar sem free_methods
        $this->info("\nTentando apenas free_shipping=true sem free_methods...");
        $resultado2 = $client->put("/items/{$itemId}", [
            'shipping' => [
                'mode' => 'me1',
                'local_pick_up' => false,
                'free_shipping' => true,
            ],
        ]);

        if ($resultado2['success']) {
            $novoShipping = $resultado2['body']['shipping'] ?? [];
            $this->info("✅ Sucesso (fallback)! mode={$novoShipping['mode']}, free_shipping=" . ($novoShipping['free_shipping'] ? 'true' : 'false'));
            return 0;
        }

        $this->error("❌ Fallback também falhou: HTTP {$resultado2['http_code']}");
        $this->line(json_encode($resultado2['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 1;
    }
}
