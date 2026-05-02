<?php

namespace App\Jobs;

use App\Models\TrocaTampoConfig;
use App\Services\Bling\BlingClient;
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
            ->first();

        if (!$config) return;

        // Sempre atualizar na primary (estoque real)
        // A secondary espelha a primary depois
        $contaEstoque = 'primary';

        // Buscar outras variações do mesmo grupo (mesma familia + cor)
        $outrasVariacoes = TrocaTampoConfig::where('familia_tampo', $config->familia_tampo)
            ->where('cor', $config->cor)
            ->where('sku_produto', '!=', $this->skuVendido)
            ->get();

        if ($outrasVariacoes->isEmpty()) return;

        $client = new BlingClient($contaEstoque);
        $depositoId = $this->getDepositoGeral($client);

        if (!$depositoId) {
            Log::error("SyncEstoqueTampo: depósito Geral não encontrado");
            return;
        }

        // Se vendeu na secondary, descontar o SKU vendido também na primary
        if ($this->accountKey === 'secondary') {
            $this->descontarNaPrimary($client, $depositoId);
        }

        foreach ($outrasVariacoes as $variacao) {
            try {
                $produto = $client->getProductBySku($variacao->sku_produto);
                if (!$produto) {
                    Log::warning("SyncEstoqueTampo: SKU {$variacao->sku_produto} não encontrado no Bling");
                    continue;
                }

                $produtoId = (int) $produto['id'];

                // Buscar saldo atual
                $saldoAtual = $this->buscarSaldo($client, $produtoId, $depositoId);
                $novoSaldo = max(0, $saldoAtual - $this->quantidadeVendida);

                if ($novoSaldo === $saldoAtual) continue;

                // Atualizar estoque (operação B = balanço)
                $res = $client->post('/estoques', [], [
                    'produto' => ['id' => $produtoId],
                    'deposito' => ['id' => $depositoId],
                    'operacao' => 'B',
                    'preco' => 0,
                    'custo' => 0,
                    'quantidade' => $novoSaldo,
                    'observacoes' => "Tampo: venda {$this->skuVendido} x{$this->quantidadeVendida}",
                ]);

                if ($res['success']) {
                    Log::info("SyncEstoqueTampo: {$variacao->sku_produto} {$saldoAtual} → {$novoSaldo} (venda {$this->skuVendido} x{$this->quantidadeVendida})");
                } else {
                    Log::warning("SyncEstoqueTampo: erro ao atualizar {$variacao->sku_produto}", ['http' => $res['http_code'] ?? null]);
                }

                usleep(200000); // rate limiting
            } catch (\Exception $e) {
                Log::error("SyncEstoqueTampo: erro {$variacao->sku_produto}: {$e->getMessage()}");
            }
        }

        // Espelhar saldos atualizados para a secondary
        $this->espelharParaSecondary($client, $depositoId, $config, $outrasVariacoes);
    }

    private function espelharParaSecondary(BlingClient $primaryClient, int $primaryDepositoId, TrocaTampoConfig $configVendido, $outrasVariacoes): void
    {
        try {
            $secondaryClient = new BlingClient('secondary');
            $secondaryDepositoId = $this->getDepositoGeral($secondaryClient);
            if (!$secondaryDepositoId) return;

            // Todos os SKUs do grupo (vendido + outras variações)
            $todosSkus = $outrasVariacoes->pluck('sku_produto')->push($this->skuVendido)->unique();

            foreach ($todosSkus as $sku) {
                $prodPrimary = $primaryClient->getProductBySku($sku);
                $prodSecondary = $secondaryClient->getProductBySku($sku);
                if (!$prodPrimary || !$prodSecondary) continue;

                $saldoPrimary = $this->buscarSaldo($primaryClient, (int) $prodPrimary['id'], $primaryDepositoId);

                $secondaryClient->post('/estoques', [], [
                    'produto' => ['id' => (int) $prodSecondary['id']],
                    'deposito' => ['id' => $secondaryDepositoId],
                    'operacao' => 'B',
                    'preco' => 0,
                    'custo' => 0,
                    'quantidade' => $saldoPrimary,
                    'observacoes' => "Espelho primary: {$sku} = {$saldoPrimary}",
                ]);

                Log::info("SyncEstoqueTampo: espelho secondary {$sku} = {$saldoPrimary}");
                usleep(200000);
            }
        } catch (\Exception $e) {
            Log::error("SyncEstoqueTampo: erro espelhar secondary: {$e->getMessage()}");
        }
    }

    private function descontarNaPrimary(BlingClient $client, int $depositoId): void
    {
        try {
            $produto = $client->getProductBySku($this->skuVendido);
            if (!$produto) return;

            $produtoId = (int) $produto['id'];
            $saldoAtual = $this->buscarSaldo($client, $produtoId, $depositoId);
            $novoSaldo = max(0, $saldoAtual - $this->quantidadeVendida);

            if ($novoSaldo === $saldoAtual) return;

            $res = $client->post('/estoques', [], [
                'produto' => ['id' => $produtoId],
                'deposito' => ['id' => $depositoId],
                'operacao' => 'B',
                'preco' => 0,
                'custo' => 0,
                'quantidade' => $novoSaldo,
                'observacoes' => "Venda HES: {$this->skuVendido} x{$this->quantidadeVendida}",
            ]);

            if ($res['success']) {
                Log::info("SyncEstoqueTampo: primary {$this->skuVendido} {$saldoAtual} → {$novoSaldo} (venda HES)");
            }
        } catch (\Exception $e) {
            Log::error("SyncEstoqueTampo: erro descontar primary {$this->skuVendido}: {$e->getMessage()}");
        }
    }

    private function buscarSaldo(BlingClient $client, int $produtoId, int $depositoId): int
    {
        $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $produtoId]);
        if (!$res['success'] || empty($res['body']['data'])) return 0;

        foreach ($res['body']['data'][0]['depositos'] ?? [] as $dep) {
            $depId = (int) ($dep['deposito']['id'] ?? $dep['id'] ?? 0);
            if ($depId === $depositoId) {
                return (int) ($dep['saldoFisico'] ?? 0);
            }
        }
        return 0;
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
