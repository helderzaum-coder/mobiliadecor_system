<?php

namespace App\Console\Commands; // <--- Certifique-se de que está exatamente assim

use Illuminate\Console\Command;
use App\Models\PedidoBlingStaging;
use App\Jobs\ReprocessarPedidoShopeeJob;

class SincronizarPedidosShopee extends Command
{
    // O nome e assinatura do comando no terminal
    protected $signature = 'shopee:sincronizar-financeiro';

    protected $description = 'Dispara jobs para atualizar os dados financeiros do staging da Shopee via API';

    public function handle()
    {
        $this->info('Buscando pedidos pendentes de conciliação financeira...');

        // Filtra registros que possuem o número da Shopee mas ainda não possuem comissão preenchida
        // Adapte os filtros com base nas regras do seu banco de dados
        $pedidos = PedidoBlingStaging::whereNotNull('numero_loja')
                                    ->whereNull('comissao') 
                                    ->get();

        if ($pedidos->isEmpty()) {
            $this->info('Nenhum pedido pendente encontrado.');
            return Command::SUCCESS;
        }

        $this->info("Enviando {$pedidos->count()} pedidos para a fila de processamento...");

        foreach ($pedidos as $pedido) {
            // Envia cada pedido individualmente para a fila do Laravel
            ReprocessarPedidoShopeeJob::dispatch($pedido);
        }

        $this->info('Todos os pedidos foram despachados com sucesso para a fila.');
        return Command::SUCCESS;
    }
}
