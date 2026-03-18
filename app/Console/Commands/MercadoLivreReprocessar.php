<?php

namespace App\Console\Commands;

use App\Models\PedidoBlingStaging;
use App\Services\MercadoLivre\MercadoLivreOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MercadoLivreReprocessar extends Command
{
    protected $signature = 'ml:reprocessar {--account=primary} {--desde=2026-01-01}';
    protected $description = 'Busca dados complementares do ML para pedidos do staging que ainda não têm info ML';

    public function handle(): int
    {
        $account = $this->option('account');
        $desde = $this->option('desde');

        $pedidos = PedidoBlingStaging::where('canal', 'like', '%mercado%')
            ->whereNull('ml_tipo_anuncio')
            ->where('data_pedido', '>=', $desde)
            ->get();

        if ($pedidos->isEmpty()) {
            $this->info('Nenhum pedido ML pendente de complemento encontrado.');
            return 0;
        }

        $this->info("Encontrados {$pedidos->count()} pedidos para complementar.");

        $mlService = new MercadoLivreOrderService($account);
        $sucesso = 0;
        $erros = 0;

        $bar = $this->output->createProgressBar($pedidos->count());
        $bar->start();

        foreach ($pedidos as $staging) {
            $orderId = $staging->numero_loja ?? $staging->numero_pedido;

            if (!$orderId) {
                $erros++;
                $bar->advance();
                continue;
            }

            try {
                $dados = $mlService->buscarDadosPedido((string) $orderId);

                if ($dados) {
                    $staging->update([
                        'ml_tipo_anuncio' => $dados['tipo_anuncio'],
                        'ml_tipo_frete' => $dados['tipo_frete'],
                        'ml_tem_rebate' => $dados['tem_rebate'],
                        'ml_valor_rebate' => $dados['valor_rebate'],
                        'ml_sale_fee' => $dados['sale_fee'],
                        'ml_frete_custo' => $dados['frete_ml_custo'],
                        'ml_frete_receita' => $dados['frete_ml_receita'],
                        'ml_order_id' => $dados['order_id'],
                        'ml_shipping_id' => $dados['shipping_id'],
                    ]);
                    $sucesso++;
                } else {
                    $this->newLine();
                    $this->warn("Pedido {$orderId}: sem dados no ML");
                    $erros++;
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Pedido {$orderId}: {$e->getMessage()}");
                Log::warning("ML reprocessar erro pedido {$orderId}", ['error' => $e->getMessage()]);
                $erros++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Concluído: {$sucesso} complementados, {$erros} erros.");

        return 0;
    }
}
