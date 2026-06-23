<?php

namespace App\Console\Commands;

use App\Models\PedidoBlingStaging;
use App\Services\MercadoLivre\MercadoLivreOrderService;
use Illuminate\Console\Command;

class MercadoLivreReprocessarDados extends Command
{
    protected $signature = 'ml:reprocessar-dados {account=secondary} {--limit=100}';
    protected $description = 'Reprocessa dados ML e auto-aprova pedidos ME2/FULL pendentes';

    public function handle(): int
    {
        $account = $this->argument('account');
        $limit   = (int) $this->option('limit');

        $pedidos = PedidoBlingStaging::where('bling_account', $account)
            ->where('status', 'pendente')
            ->where(function ($q) {
                $q->whereNull('ml_tipo_anuncio')
                  ->orWhereNull('ml_tipo_frete');
            })
            ->where(function ($q) {
                $q->where('canal', 'like', '%mercado%')
                  ->orWhere('canal', 'like', '%Mercado%')
                  ->orWhere('numero_loja', 'like', '2000%');
            })
            ->limit($limit)
            ->get();

        if ($pedidos->isEmpty()) {
            $this->info('Nenhum pedido pendente sem dados ML.');
            return 0;
        }

        $this->info("Reprocessando {$pedidos->count()} pedido(s) da conta {$account}...");
        $service = new MercadoLivreOrderService($account);

        $ok = 0; $erro = 0; $aprovados = 0;
        foreach ($pedidos as $pedido) {
            $orderId = $pedido->numero_loja ?? $pedido->numero_pedido;
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

                    // Auto-aprovar ME2/FULL
                    if (in_array($dados['tipo_frete'], ['ME2', 'FULL']) && $pedido->status === 'pendente') {
                        try {
                            // Recalcular comissão com dados reais antes de aprovar
                            $isMeOrFull = true;
                            $mlSaleFee = (float) ($dados['sale_fee'] ?? 0);
                            $mlFreteCusto = (float) ($dados['frete_ml_custo'] ?? 0);
                            $mlFreteReceita = (float) ($dados['frete_ml_receita'] ?? 0);

                            if ($mlSaleFee > 0) {
                                $freteLiquido = round($mlFreteCusto - $mlFreteReceita, 2);
                                $pedido->update([
                                    'comissao_calculada' => round($mlSaleFee + $freteLiquido, 2),
                                    'custo_frete' => 0,
                                ]);
                            }

                            $pedido->refresh();
                            \App\Services\AprovacaoVendaService::aprovar($pedido);
                            $aprovados++;
                            $this->info("    → Auto-aprovado (ME2/FULL)");
                        } catch (\Exception $e) {
                            $this->warn("    → Falha auto-aprovação: {$e->getMessage()}");
                        }
                    }
                } else {
                    $this->warn("  ✗ {$orderId} — não encontrado na API ML");
                    $erro++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ {$orderId} — {$e->getMessage()}");
                $erro++;
            }
        }

        $this->info("Concluído: {$ok} atualizados, {$aprovados} auto-aprovados, {$erro} erros.");
        return 0;
    }
}
