<?php

namespace App\Jobs;

use App\Models\ProdutoEstoque;
use App\Services\Bling\BlingClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompararEstoqueBlingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(
        private readonly string $filtro = 'divergencias',
        private readonly ?int $userId = null,
        private readonly string $filtroTipo = 'todos'
    ) {}

    public function handle(): void
    {
        Cache::put('comparar_bling_running', true, 900);

        try {
            $primary = new BlingClient('primary');
            $secondary = new BlingClient('secondary');

            $depositoPrimary = $this->getDeposito($primary);
            $depositoSecondary = $this->getDeposito($secondary);

            if (!$depositoPrimary || !$depositoSecondary) {
                Log::error('CompararEstoqueBlingJob: depósito não encontrado');
                return;
            }

            $query = ProdutoEstoque::where('ativo', true)->orderBy('sku');

            if ($this->filtroTipo === 'simples') {
                $query->whereNotIn('formato', ['E', 'C']);
            } elseif ($this->filtroTipo === 'kit') {
                $query->whereIn('formato', ['E', 'C']);
            }

            $produtos = $query->get();
            $resultados = [];
            $totalProdutos = 0;
            $totalDivergencias = 0;

            foreach ($produtos as $produto) {
                $saldoPrimary = $this->getSaldo($primary, $produto->sku, $depositoPrimary);
                $saldoSecondary = $this->getSaldo($secondary, $produto->sku, $depositoSecondary);
                $divergente = $saldoPrimary !== $saldoSecondary;

                $totalProdutos++;
                if ($divergente) $totalDivergencias++;

                if ($this->filtro === 'divergencias' && !$divergente) continue;

                $resultados[] = [
                    'sku' => $produto->sku,
                    'nome' => $produto->nome,
                    'sistema' => $produto->saldo,
                    'primary' => $saldoPrimary,
                    'secondary' => $saldoSecondary,
                    'divergente' => $divergente,
                ];
            }

            Cache::put('comparar_bling_resultado', [
                'resultados' => $resultados,
                'totalProdutos' => $totalProdutos,
                'totalDivergencias' => $totalDivergencias,
                'filtro' => $this->filtro,
                'gerado_em' => now()->format('d/m/Y H:i'),
            ], 3600);

            Log::info("CompararEstoqueBlingJob: concluído", [
                'total' => $totalProdutos,
                'divergencias' => $totalDivergencias,
            ]);

            if ($this->userId) {
                $user = \App\Models\User::find($this->userId);
                if ($user) {
                    \Filament\Notifications\Notification::make()
                        ->title("Comparação Bling concluída")
                        ->body("Total: {$totalProdutos} | Divergências: {$totalDivergencias}")
                        ->icon($totalDivergencias === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                        ->iconColor($totalDivergencias === 0 ? 'success' : 'warning')
                        ->sendToDatabase($user);
                }
            }
        } finally {
            Cache::forget('comparar_bling_running');
        }
    }

    private function getSaldo(BlingClient $client, string $sku, int $depositoId): ?int
    {
        $produto = $client->getProductBySku($sku);
        if (!$produto) return null;

        $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $produto['id']]);
        if (!$res['success'] || empty($res['body']['data'])) return null;

        $dados = $res['body']['data'][0] ?? null;
        if (!$dados) return null;

        foreach ($dados['depositos'] ?? [] as $dep) {
            $depId = (int) ($dep['deposito']['id'] ?? $dep['id'] ?? 0);
            if ($depId === $depositoId) {
                return (int) ($dep['saldoFisico'] ?? 0);
            }
        }

        return 0;
    }

    private function getDeposito(BlingClient $client): ?int
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
