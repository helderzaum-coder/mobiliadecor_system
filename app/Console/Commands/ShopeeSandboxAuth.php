<?php

namespace App\Console\Commands;

use App\Services\Shopee\ShopeeClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeSandboxAuth extends Command
{
    protected $signature = 'shopee:sandbox-auth {action=url : url|token|test-orders} {--code= : Code recebido no callback} {--shop-id=226991207 : Shop ID sandbox}';
    protected $description = '[HOMOLOGAÇÃO] Fluxo de auth e teste sandbox - NÃO afeta dados reais';

    public function handle()
    {
        $this->warn('⚠️  MODO SANDBOX/HOMOLOGAÇÃO - Nenhum dado real será afetado');
        $this->newLine();

        $action = $this->argument('action');

        match ($action) {
            'url'         => $this->generateAuthUrl(),
            'token'       => $this->exchangeToken(),
            'test-orders' => $this->testOrders(),
            default       => $this->error("Ações: url, token, test-orders"),
        };

        return Command::SUCCESS;
    }

    private function generateAuthUrl(): void
    {
        $client = new ShopeeClient();
        $path = '/api/v2/shop/auth_partner';
        $ts = time();
        $sign = $client->sign($path, $ts);

        $redirect = 'https://shopee.com';

        $url = $client->getHost() . $path
            . "?partner_id=" . $client->getPartnerId()
            . "&timestamp={$ts}"
            . "&sign={$sign}"
            . "&redirect={$redirect}";

        $this->info('🔗 URL de autorização sandbox:');
        $this->newLine();
        $this->line($url);
        $this->newLine();
        $this->info('1) Abra no navegador e faça login na conta sandbox');
        $this->info('2) Após autorizar, copie o "code" da URL de redirecionamento');
        $this->info('3) Rode: php artisan shopee:sandbox-auth token --code=SEU_CODE');
    }

    private function exchangeToken(): void
    {
        $code = $this->option('code');
        $shopId = (int) $this->option('shop-id');

        if (!$code) {
            $this->error('Informe o code: --code=XXXXXXX');
            return;
        }

        $this->info("🔑 Trocando code por access_token (Shop: {$shopId})...");

        $client = new ShopeeClient();
        $result = $client->getAccessToken($code, $shopId);

        if ($result && !empty($result['access_token'])) {
            $this->info('✅ Token obtido com sucesso!');
            $this->line("Access Token: " . substr($result['access_token'], 0, 20) . '...');
            $this->line("Refresh Token: " . substr($result['refresh_token'], 0, 20) . '...');
            $this->line("Expira em: " . ($result['expire_in'] ?? '?') . " segundos");
            $this->newLine();
            $this->info('Agora teste: php artisan shopee:sandbox-auth test-orders');
        } else {
            $this->error('❌ Falha ao obter token.');
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private function testOrders(): void
    {
        $shopId = (int) $this->option('shop-id');
        $client = new ShopeeClient();

        if (!$client->isAuthorized($shopId)) {
            $this->error('❌ Não autorizado. Complete o fluxo de auth primeiro.');
            return;
        }

        $this->info("📦 [SANDBOX] Buscando pedidos de teste (Shop: {$shopId})...");

        $result = $client->get('/api/v2/order/get_order_list', [
            'time_range_field' => 'create_time',
            'time_from'        => strtotime('-30 days'),
            'time_to'          => time(),
            'page_size'        => 50,
            'order_status'     => 'ALL',
        ], $shopId);

        $this->newLine();
        $this->warn('📋 Response completa (dados de TESTE apenas):');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (!$result['success']) {
            $this->error('❌ Erro na requisição.');
            return;
        }

        $orders = $result['body']['response']['order_list'] ?? [];

        if (empty($orders)) {
            $this->warn('Nenhum pedido de teste encontrado.');
            return;
        }

        $this->newLine();
        $this->info("✅ " . count($orders) . " pedidos de teste encontrados:");

        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                $order['order_sn'],
                $order['order_status'],
                date('d/m/Y H:i', $order['create_time'] ?? 0),
            ];
        }
        $this->table(['Pedido', 'Status', 'Criado em'], $rows);
    }
}
