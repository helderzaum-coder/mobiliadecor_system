<?php

namespace App\Console\Commands;

use App\Services\Shopee\ShopeeClient;
use Illuminate\Console\Command;

class ShopeeSandboxTest extends Command
{
    protected $signature = 'shopee:sandbox-test {action=auth : auth|orders|status}';
    protected $description = 'Testa integração sandbox Shopee (auth, orders, status)';

    public function handle()
    {
        $client = new ShopeeClient();
        $action = $this->argument('action');

        match ($action) {
            'auth'   => $this->testAuth($client),
            'orders' => $this->testOrders($client),
            'status' => $this->testStatus($client),
            default  => $this->error("Ação inválida. Use: auth, orders, status"),
        };

        return Command::SUCCESS;
    }

    private function testAuth(ShopeeClient $client): void
    {
        $this->info('🔗 Gerando URL de autorização sandbox...');
        $this->newLine();
        $url = $client->getAuthUrl();
        $this->line("Acesse esta URL no navegador para autorizar:");
        $this->newLine();
        $this->line($url);
        $this->newLine();
        $this->info("Após autorizar, o callback salvará o access_token automaticamente.");
        $this->info("Depois rode: php artisan shopee:sandbox-test orders");
    }

    private function testOrders(ShopeeClient $client): void
    {
        $shopId = (int) config('shopee.shop_id');

        if (!$client->isAuthorized($shopId)) {
            $this->error('❌ Não autorizado. Rode primeiro: php artisan shopee:sandbox-test auth');
            return;
        }

        $this->info("🛒 Buscando pedidos dos últimos 15 dias (Shop: {$shopId})...");

        $result = $client->get('/api/v2/order/get_order_list', [
            'time_range_field' => 'create_time',
            'time_from'        => strtotime('-15 days'),
            'time_to'          => time(),
            'page_size'        => 50,
            'order_status'     => 'ALL',
        ], $shopId);

        if (!$result['success']) {
            $this->error('❌ Erro: ' . json_encode($result['body'] ?? $result['error'] ?? 'desconhecido'));
            return;
        }

        $orders = $result['body']['response']['order_list'] ?? [];

        if (empty($orders)) {
            $this->warn('⚠️ Nenhum pedido encontrado no período.');
            $this->line(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return;
        }

        $this->info("✅ Encontrados " . count($orders) . " pedidos:");
        $this->newLine();

        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                $order['order_sn'],
                $order['order_status'],
                date('d/m/Y H:i', $order['create_time'] ?? 0),
            ];
        }

        $this->table(['Pedido', 'Status', 'Criado em'], $rows);

        // Buscar detalhes do primeiro pedido
        $firstSn = $orders[0]['order_sn'];
        $this->newLine();
        $this->info("📦 Detalhes do pedido: {$firstSn}");

        $detail = $client->get('/api/v2/order/get_order_detail', [
            'order_sn_list' => $firstSn,
            'response_optional_fields' => 'item_list,buyer_user_id,pay_time,total_amount',
        ], $shopId);

        if ($detail['success']) {
            $this->line(json_encode($detail['body']['response'] ?? $detail['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error('Erro ao buscar detalhes: ' . json_encode($detail['body']));
        }
    }

    private function testStatus(ShopeeClient $client): void
    {
        $shopId = (int) config('shopee.shop_id');
        $authorized = $client->isAuthorized($shopId);

        $this->info("📊 Status da integração Shopee Sandbox");
        $this->line("Partner ID : " . config('shopee.partner_id'));
        $this->line("Shop ID    : {$shopId}");
        $this->line("Host       : " . $client->getHost());
        $this->line("Autorizado : " . ($authorized ? '✅ Sim' : '❌ Não'));
    }
}
