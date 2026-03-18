<?php

namespace App\Console\Commands;

use App\Models\PedidoBlingStaging;
use App\Services\MercadoLivre\MercadoLivreOrderService;
use Illuminate\Console\Command;

class MercadoLivreReprocessarDados extends Command
{
    protected $signature = 'ml:reprocessar-dados {account=secondary} {--limit=100}';
    protected $description = 'Reprocessa tipo_anuncio, tipo_frete e sale_fee dos pedidos ML já importados';

    public function handle(): int
    {
        $account = $this->argument('account');
        $limit   = (int) $this->option('limit');

        $pedidos = PedidoBlingStaging::where('bling_account', $account)
            ->where('status', 'pendente')
            ->whereNull('ml_tipo_anuncio')
            ->limit($limit)
            ->get();

        if ($pedidos->isEmpty()) {
            $this->info('Nenhum pedido pendente sem dados ML.');
            return 0;
        }

        $this->info("Reprocessando {$pedidos->count()} pedido(s) da conta {$account}...");
        $service = new MercadoLivreOrderService($account);

        $ok = 0; $erro = 0;
        foreach ($pedidos as $pedido) {
            $orderId = $pedido->numero_loja;
            try {
                $dados = $service->buscarDadosPedido((string) $orderId);
                if ($dados) {
                    $pedido->update([
                        'ml_tipo_anuncio' => $dados['tipo_anuncio'],
                        'ml_tipo_frete'   => $dados['tipo_frete'],
                        'ml_sale_fee'     => $dados['sale_fee'],
                        'ml_frete_custo'  => $dados['frete_ml_custo'],
                        'ml_frete_receita'=> $dados['frete_ml_receita'],
                        'ml_order_id'     => $dados['order_id'],
                        'ml_shipping_id'  => $dados['shipping_id'],
                    ]);
                    $this->line("  ✓ {$orderId} — {$dados['tipo_anuncio']} / {$dados['tipo_frete']}");
                    $ok++;
                } else {
                    $this->warn("  ✗ {$orderId} — não encontrado na API ML");
                    $erro++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ {$orderId} — {$e->getMessage()}");
                $erro++;
            }
        }

        $this->info("Concluído: {$ok} atualizados, {$erro} erros.");
        return 0;
    }
}
