<?php

namespace App\Jobs;

use App\Models\ProdutoEstoque;
use App\Models\TrocaTampoConfig;
use App\Services\Bling\BlingClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncEstoqueBlingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [5, 15, 30];

    public function __construct(
        private readonly string $sku,
        private readonly int $saldo,
        private readonly ?string $observacao = null,
        private readonly string $operacao = 'B'
    ) {}

    public function handle(): void
    {
        $saldoLimitado = $this->aplicarLimiteTampo($this->sku, $this->saldo);

        foreach (['primary', 'secondary'] as $account) {
            $this->atualizarConta($account, $saldoLimitado);
        }
    }

    /**
     * Limita o saldo pelo estoque do tampo correspondente.
     */
    private function aplicarLimiteTampo(string $sku, int $saldo): int
    {
        $config = TrocaTampoConfig::where('sku_produto', $sku)
            ->where('equalizacao_ativa', true)
            ->first();

        if (!$config || empty($config->sku_tampo)) {
            return $saldo;
        }

        $tampo = ProdutoEstoque::where('sku', $config->sku_tampo)->where('ativo', true)->first();
        if (!$tampo) {
            return $saldo;
        }

        if ($tampo->saldo < $saldo) {
            Log::info("SyncEstoqueBling: SKU {$sku} limitado por tampo {$tampo->sku} ({$saldo} → {$tampo->saldo})");
            return $tampo->saldo;
        }

        return $saldo;
    }

    private function atualizarConta(string $account, int $saldoFinal): void
    {
        $client = new BlingClient($account);

        $produto = $client->getProductBySku($this->sku);
        if (!$produto) {
            Log::warning("SyncEstoqueBling: SKU {$this->sku} nao encontrado na {$account}");
            return;
        }

        $produtoId = (int) $produto['id'];
        $depositoId = $this->getDepositoGeral($client);
        if (!$depositoId) {
            Log::error("SyncEstoqueBling: deposito nao encontrado na {$account}");
            return;
        }

        Cache::put("bling_sync_loop_{$account}_{$produtoId}", true, 60);

        $res = $this->operacao === 'B'
            ? $this->balancoEstoque($client, $produtoId, $depositoId, $saldoFinal)
            : $this->movimentarEstoque($client, $produtoId, $depositoId, $saldoFinal);

        if ($res['success']) {
            Log::info("SyncEstoqueBling: {$account} SKU {$this->sku} {$this->operacao} {$saldoFinal}");
        } else {
            Log::warning("SyncEstoqueBling: erro {$account} SKU {$this->sku}", ['http' => $res['http_code'] ?? null]);
        }
    }

    private function movimentarEstoque(BlingClient $client, int $produtoId, int $depositoId, int $saldoFinal): array
    {
        return $client->post('/estoques', [], [
            'produto' => ['id' => $produtoId],
            'deposito' => ['id' => $depositoId],
            'operacao' => $this->operacao,
            'preco' => 0,
            'custo' => 0,
            'quantidade' => max(0, $saldoFinal),
            'observacoes' => $this->observacao ?: "Sistema: SKU {$this->sku} {$this->operacao} {$saldoFinal}",
        ]);
    }

    private function balancoEstoque(BlingClient $client, int $produtoId, int $depositoId, int $saldoFinal): array
    {
        return $client->post('/estoques', [], [
            'produto' => ['id' => $produtoId],
            'deposito' => ['id' => $depositoId],
            'operacao' => 'B',
            'preco' => 0,
            'custo' => 0,
            'quantidade' => max(0, $saldoFinal),
            'observacoes' => $this->observacao ?: "Sistema: SKU {$this->sku} = {$saldoFinal}",
        ]);
    }

    private function getDepositoGeral(BlingClient $client): ?int
    {
        $res = $client->get('/depositos', ['limite' => 100]);
        if (!$res['success']) return null;

        foreach ($res['body']['data'] ?? [] as $d) {
            if (str_contains(strtolower(trim($d['descricao'] ?? '')), 'geral')) {
                return (int) $d['id'];
            }
        }

        return (int) (($res['body']['data'][0] ?? [])['id'] ?? 0) ?: null;
    }
}
