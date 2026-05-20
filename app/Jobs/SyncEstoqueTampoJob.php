<?php

namespace App\Jobs;

use App\Models\ProdutoEstoque;
use App\Models\TrocaTampoConfig;
use App\Services\EstoqueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEstoqueTampoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private readonly string $skuVendido,
        private readonly int $quantidadeVendida,
        private readonly string $accountKey = 'primary'
    ) {}

    public function handle(): void
    {
        $config = TrocaTampoConfig::where('sku_produto', $this->skuVendido)
            ->whereNotNull('familia_tampo')
            ->where('familia_tampo', '!=', '')
            ->where('equalizacao_ativa', true)
            ->first();

        if (!$config) return;

        // Buscar outras variações do mesmo grupo (mesma familia + cor)
        $outrasVariacoes = TrocaTampoConfig::where('familia_tampo', $config->familia_tampo)
            ->where('cor', $config->cor)
            ->where('sku_produto', '!=', $this->skuVendido)
            ->where('equalizacao_ativa', true)
            ->get();

        if ($outrasVariacoes->isEmpty()) return;

        foreach ($outrasVariacoes as $variacao) {
            $produto = ProdutoEstoque::where('sku', $variacao->sku_produto)->where('ativo', true)->first();
            if (!$produto) {
                Log::warning("SyncEstoqueTampo: SKU {$variacao->sku_produto} não encontrado no estoque interno");
                continue;
            }

            $res = EstoqueService::saida(
                $variacao->sku_produto,
                $this->quantidadeVendida,
                'sync',
                "Tampo: venda {$this->skuVendido} x{$this->quantidadeVendida}"
            );

            // Limitar pelo estoque do tampo correspondente
            if ($res['success']) {
                $produtoAtualizado = $produto->fresh();
                $tampo = ProdutoEstoque::where('sku', $variacao->sku_tampo)->where('ativo', true)->first();
                if ($tampo && $produtoAtualizado->saldo > $tampo->saldo) {
                    // Reduzir virtual para que saldo total = tampo->saldo
                    $virtualNecessario = max(0, $tampo->saldo - $produtoAtualizado->saldo_fisico);
                    EstoqueService::balanco(
                        $variacao->sku_produto,
                        $virtualNecessario,
                        'sync',
                        "Limitado por tampo {$tampo->sku} (={$tampo->saldo})",
                        null,
                        true,
                        'virtual'
                    );
                    Log::info("SyncEstoqueTampo: {$variacao->sku_produto} limitado por tampo {$tampo->sku} → {$tampo->saldo}");
                } else {
                    Log::info("SyncEstoqueTampo: {$variacao->sku_produto} → saldo {$res['saldo']} (venda {$this->skuVendido} x{$this->quantidadeVendida})");
                }
            } else {
                Log::warning("SyncEstoqueTampo: erro {$variacao->sku_produto}: " . ($res['erro'] ?? '?'));
            }
        }
    }
}
