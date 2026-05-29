<?php

namespace App\Console\Commands;

use App\Services\MercadoLivre\MercadoLivreClient;
use Illuminate\Console\Command;

class MercadoLivreDebugItem extends Command
{
    protected $signature = 'ml:debug-item {item_id} {--account=primary} {--zip=01310100}';
    protected $description = 'Busca JSON completo de um item ML e suas opcoes de envio';

    public function handle(): int
    {
        $itemId = strtoupper((string) $this->argument('item_id'));
        if (!str_starts_with($itemId, 'MLB')) {
            $itemId = 'MLB' . $itemId;
        }

        $account = (string) $this->option('account');
        $zipCode = (string) $this->option('zip');
        $client = new MercadoLivreClient($account);

        $this->info("=== ITEM /items/{$itemId} ===");
        $item = $client->get("/items/{$itemId}");
        $this->line(json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info("=== SHIPPING OPTIONS /items/{$itemId}/shipping_options?zip_code={$zipCode} ===");
        $shippingOptions = $client->get("/items/{$itemId}/shipping_options", ['zip_code' => $zipCode]);
        $this->line(json_encode($shippingOptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
