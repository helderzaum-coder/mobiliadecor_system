<?php

namespace App\Console\Commands;

use App\Services\MercadoLivre\MercadoLivreClient;
use Illuminate\Console\Command;

class MercadoLivreDebugPedido extends Command
{
    protected $signature = 'ml:debug-pedido {order_id} {--account=primary}';
    protected $description = 'Busca JSON completo de um pedido ML (order + shipping)';

    public function handle(): int
    {
        $orderId = $this->argument('order_id');
        $account = $this->option('account');

        $client = new MercadoLivreClient($account);

        $this->info("=== ORDER /orders/{$orderId} ===");
        $order = $client->get("/orders/{$orderId}");
        $this->line(json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($order['success']) {
            $shippingId = $order['body']['shipping']['id'] ?? null;
            if ($shippingId) {
                $this->newLine();
                $this->info("=== SHIPPING /shipments/{$shippingId} ===");
                $shipping = $client->get("/shipments/{$shippingId}");
                $this->line(json_encode($shipping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Billing info - dados financeiros reais (tarifa, estorno, total)
            $this->newLine();
            $this->info("=== BILLING /orders/{$orderId}/billing_info ===");
            $billing = $client->get("/orders/{$orderId}/billing_info");
            $this->line(json_encode($billing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Payment details - detalhes do pagamento
            $paymentId = $order['body']['payments'][0]['id'] ?? null;
            if ($paymentId) {
                $this->newLine();
                $this->info("=== PAYMENT /collections/{$paymentId} ===");
                $payment = $client->get("/collections/{$paymentId}");
                $this->line(json_encode($payment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        return 0;
    }
}
