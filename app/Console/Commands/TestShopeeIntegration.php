<?php

namespace App\Console\Commands;

use App\Services\Shopee\ShopeeService;
use Illuminate\Console\Command;

class TestShopeeIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:test-integration {--order-sn= : Número do pedido Shopee para testar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa a integração Shopee com laraditz/shopee';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🛒 Testando integração Shopee...');

        // Testa se está autorizado
        $authorized = ShopeeService::isAuthorized();
        $this->info($authorized ? '✅ Integração autorizada' : '❌ Integração não autorizada');

        if (!$authorized) {
            $this->error('Configure as credenciais SHOPEE_* no .env e autorize a integração');
            return Command::FAILURE;
        }

        // Testa busca de pedido se fornecido
        $orderSn = $this->option('order-sn');
        if ($orderSn) {
            $this->info("🔍 Buscando pedido: {$orderSn}");

            $orderDetail = ShopeeService::getOrderDetail($orderSn);
            if ($orderDetail) {
                $this->info('✅ Pedido encontrado:');
                $this->line(json_encode($orderDetail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error('❌ Pedido não encontrado ou erro na API');
            }

            $escrowDetail = ShopeeService::getEscrowDetail($orderSn);
            if ($escrowDetail) {
                $this->info('✅ Dados financeiros encontrados:');
                $this->line(json_encode($escrowDetail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error('❌ Dados financeiros não encontrados');
            }
        }

        // Testa busca de lista de pedidos recentes
        $this->info('📋 Buscando pedidos recentes (últimos 7 dias)...');
        $timeFrom = strtotime('-7 days');
        $timeTo = time();

        $orders = ShopeeService::getOrderList($timeFrom, $timeTo);
        if (!empty($orders['order_list'])) {
            $count = count($orders['order_list']);
            $this->info("✅ Encontrados {$count} pedidos recentes");
            foreach (array_slice($orders['order_list'], 0, 3) as $order) {
                $this->line("- {$order['order_sn']} ({$order['order_status']})");
            }
        } else {
            $this->warn('⚠️ Nenhum pedido recente encontrado ou erro na API');
        }

        $this->info('🎉 Teste concluído!');
        return Command::SUCCESS;
    }
}