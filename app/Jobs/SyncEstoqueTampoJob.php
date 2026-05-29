<?php

namespace App\Jobs;

use App\Models\ProdutoEstoque;
use App\Models\TrocaTampoConfig;
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
    public int $timeout = 300;

    public function __construct(
        private readonly string $skuVendido,
        private readonly int $quantidadeVendida,
        private readonly string $accountKey = 'primary',
        private readonly ?int $numeroPedido = null
    ) {}

    public function handle(): void
    {
        $config = TrocaTampoConfig::where('sku_produto', $this->skuVendido)
            ->whereNotNull('familia_tampo')
            ->where('familia_tampo', '!=', '')
            ->where('equalizacao_ativa', true)
            ->first();

        if (!$config) return;

        // 1) Baixar UMA carcaça do grupo (grupo+cor) por unidade vendida.
        //    Prioriza a carcaça do próprio SKU vendido; se não houver, pega de um
        //    irmão do mesmo grupo+cor que tenha carcaça disponível (carcaças são
        //    compartilhadas dentro do grupo+cor).
        for ($i = 0; $i < $this->quantidadeVendida; $i++) {
            $this->baixarUmaCarcaca($config);
        }

        // 2) Reequalizar apenas a família afetada: recalcula físico = min(carcaças do
        //    grupo+cor, tampo) e sincroniza com os dois Blings. Como a soma de carcaças
        //    foi reduzida, o saldo correto será refletido sem ser "desfeito" depois.
        VariacaoTamposJob::executar($this->accountKey, $config->familia_tampo);
    }

    /**
     * Decrementa uma carcaça do grupo+cor do produto vendido.
     * Ordem de prioridade:
     *   1. O próprio SKU vendido (se saldo_carcaca > 0)
     *   2. Qualquer irmão do mesmo grupo+cor com saldo_carcaca > 0 (maior primeiro)
     */
    private function baixarUmaCarcaca(TrocaTampoConfig $config): void
    {
        // Tentar o próprio SKU vendido primeiro
        $produtoVendido = ProdutoEstoque::where('sku', $config->sku_produto)->where('ativo', true)->first();

        if ($produtoVendido && (int) $produtoVendido->saldo_carcaca > 0) {
            $produtoVendido->decrement('saldo_carcaca');
            Log::info("SyncEstoqueTampo: carcaça baixada do próprio SKU {$config->sku_produto} (venda #{$this->numeroPedido})");
            return;
        }

        // Buscar irmãos do mesmo grupo+cor com carcaça disponível
        $irmaos = TrocaTampoConfig::where('grupo', $config->grupo)
            ->where('cor', $config->cor)
            ->where('sku_produto', '!=', $config->sku_produto)
            ->pluck('sku_produto')
            ->toArray();

        if (!empty($irmaos)) {
            $irmaoComCarcaca = ProdutoEstoque::whereIn('sku', $irmaos)
                ->where('ativo', true)
                ->where('saldo_carcaca', '>', 0)
                ->orderByDesc('saldo_carcaca')
                ->first();

            if ($irmaoComCarcaca) {
                $irmaoComCarcaca->decrement('saldo_carcaca');
                Log::info("SyncEstoqueTampo: carcaça baixada do irmão {$irmaoComCarcaca->sku} (venda de {$config->sku_produto}, grupo {$config->grupo}/{$config->cor}, pedido #{$this->numeroPedido})");
                return;
            }
        }

        Log::warning("SyncEstoqueTampo: nenhuma carcaça disponível no grupo {$config->grupo}/{$config->cor} para baixar (venda de {$config->sku_produto}, pedido #{$this->numeroPedido})");
    }
}
