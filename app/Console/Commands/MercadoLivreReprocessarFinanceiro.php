<?php

namespace App\Console\Commands;

use App\Models\PedidoBlingStaging;
use App\Services\AprovacaoVendaService;
use App\Services\MercadoLivre\MercadoLivreOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MercadoLivreReprocessarFinanceiro extends Command
{
    protected $signature = 'ml:reprocessar-financeiro
        {--account=primary : Conta ML (primary/secondary)}
        {--status=pendente : Status dos pedidos (pendente/aprovado/todos)}
        {--limit=200 : Limite de pedidos}
        {--force : Reprocessar mesmo pedidos que já têm sale_fee}
        {--auto-aprovar : Auto-aprovar ME2/FULL após reprocessar}';

    protected $description = 'Rebusca dados financeiros (comissão, frete, rebate) da API do ML para pedidos antigos';

    public function handle(): int
    {
        $account = $this->option('account');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $autoAprovar = $this->option('auto-aprovar');

        $query = PedidoBlingStaging::where('bling_account', $account)
            ->where(function ($q) {
                $q->where('canal', 'like', '%mercado%')
                  ->orWhere('canal', 'like', '%meli%')
                  ->orWhere('numero_loja', 'like', '2000%');
            })
            ->limit($limit);

        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('ml_sale_fee')
                  ->orWhere('ml_sale_fee', 0);
            });
        }

        $pedidos = $query->get();

        if ($pedidos->isEmpty()) {
            $this->info('Nenhum pedido ML encontrado para reprocessar.');
            return 0;
        }

        $this->info("Reprocessando {$pedidos->count()} pedido(s) da conta {$account}...");
        $service = new MercadoLivreOrderService($account);

        $ok = 0;
        $erro = 0;
        $bar = $this->output->createProgressBar($pedidos->count());
        $bar->start();

        foreach ($pedidos as $pedido) {
            $orderId = $pedido->numero_loja ?? $pedido->numero_pedido;

            try {
                $dados = $service->buscarDadosPedido((string) $orderId);

                if ($dados) {
                    $updates = [
                        'ml_tipo_anuncio' => $dados['tipo_anuncio'],
                        'ml_tipo_frete' => $dados['tipo_frete'],
                        'ml_tem_rebate' => $dados['tem_rebate'],
                        'ml_valor_rebate' => $dados['valor_rebate'],
                        'ml_sale_fee' => $dados['sale_fee'],
                        'ml_frete_custo' => $dados['frete_ml_custo'],
                        'ml_frete_receita' => $dados['frete_ml_receita'],
                        'ml_order_id' => $dados['order_id'],
                        'ml_shipping_id' => $dados['shipping_id'],
                    ];

                    // Zerar custo_frete e atualizar comissao_calculada para ME2/FULL
                    if (in_array($dados['tipo_frete'], ['ME2', 'FULL'])) {
                        $updates['custo_frete'] = 0;
                        $taxaFrete = $dados['frete_ml_custo'] > 0 ? ($dados['frete_ml_custo'] - $dados['frete_ml_receita']) : 0;
                        $updates['comissao_calculada'] = round($dados['sale_fee'] + $taxaFrete, 2);
                    } elseif ($dados['sale_fee'] > 0) {
                        $updates['comissao_calculada'] = $dados['sale_fee'];
                    }

                    $pedido->update($updates);
                    $ok++;

                    // Auto-aprovar ME2/FULL se flag ativa e pedido pendente
                    if ($autoAprovar && $pedido->status === 'pendente' && in_array($dados['tipo_frete'], ['ME2', 'FULL'])) {
                        try {
                            AprovacaoVendaService::aprovar($pedido);
                            $this->newLine();
                            $this->line("  ✓ {$orderId} auto-aprovado ({$dados['tipo_frete']})");
                        } catch (\Exception $e) {
                            $this->newLine();
                            $this->warn("  ✗ {$orderId} erro ao aprovar: {$e->getMessage()}");
                        }
                    }
                } else {
                    $erro++;
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("  Pedido {$orderId}: {$e->getMessage()}");
                $erro++;
            }

            $bar->advance();
            usleep(150000); // rate limiting
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Concluído: {$ok} atualizados, {$erro} erros.");

        return 0;
    }
}
