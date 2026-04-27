<?php

namespace App\Jobs;

use App\Services\Bling\BlingClient;
use App\Services\Bling\BlingEstoquePedidoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EspelharEstoqueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function handle(): void
    {
        $lockKey = 'espelhar_estoque_running';
        if (Cache::has($lockKey)) {
            Log::warning('EspelharEstoque: já em execução, pulando');
            return;
        }
        Cache::put($lockKey, true, 600);

        try {
            $resultado = self::executar();

            $admins = \App\Models\User::role('admin')->get();
            foreach ($admins as $admin) {
                \Filament\Notifications\Notification::make()
                    ->title("Espelhamento de estoque concluído")
                    ->body("Atualizados: {$resultado['atualizados']} | Erros: {$resultado['erros']} | Ignorados: {$resultado['ignorados']}")
                    ->icon($resultado['erros'] === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->iconColor($resultado['erros'] === 0 ? 'success' : 'warning')
                    ->sendToDatabase($admin);
            }
        } finally {
            Cache::forget($lockKey);
        }
    }

    public static function executar(): array
    {
        $clientPrimary = new BlingClient('primary');
        $clientSecondary = new BlingClient('secondary');

        $resultado = ['atualizados' => 0, 'erros' => 0, 'ignorados' => 0, 'log' => []];

        // Buscar depósito Geral da Secondary
        $depositoSecondaryId = self::getDepositoGeral($clientSecondary);
        if (!$depositoSecondaryId) {
            Log::error('EspelharEstoque: depósito Geral não encontrado na Secondary');
            return ['atualizados' => 0, 'erros' => 1, 'ignorados' => 0, 'log' => ['Depósito não encontrado na Secondary']];
        }

        // Buscar todos os produtos da Primary (paginado)
        $pagina = 1;
        $limite = 100;

        do {
            $res = $clientPrimary->get('/produtos', ['pagina' => $pagina, 'limite' => $limite, 'tipo' => 'P']);
            if (!$res['success']) {
                $resultado['erros']++;
                $resultado['log'][] = "Erro ao buscar produtos página {$pagina}";
                break;
            }

            $produtos = $res['body']['data'] ?? [];
            if (empty($produtos)) break;

            foreach ($produtos as $produto) {
                $sku = $produto['codigo'] ?? '';
                $prodPrimaryId = (int) ($produto['id'] ?? 0);
                if (empty($sku) || !$prodPrimaryId) continue;

                // Ignorar kits/compostos — só sincronizar produtos simples e variações
                $formato = strtoupper($produto['formato'] ?? 'S');
                if (in_array($formato, ['E', 'C'])) continue;

                // Buscar saldo total da Primary (Geral + Virtual)
                $saldoPrimary = self::buscarSaldoTotal($clientPrimary, $prodPrimaryId);
                if ($saldoPrimary === null) {
                    $resultado['ignorados']++;
                    continue;
                }

                // Buscar produto na Secondary pelo SKU
                $prodSecondary = $clientSecondary->getProductBySku($sku);
                if (!$prodSecondary) {
                    $resultado['ignorados']++;
                    continue;
                }
                $prodSecondaryId = (int) $prodSecondary['id'];

                // Buscar saldo atual da Secondary
                $saldoSecondary = self::buscarSaldoTotal($clientSecondary, $prodSecondaryId);

                // Só atualizar se diferente
                if ($saldoSecondary !== null && $saldoSecondary === $saldoPrimary) {
                    $resultado['ignorados']++;
                    continue;
                }

                // Gravar na Secondary
                $res = $clientSecondary->post('/estoques', [], [
                    'produto' => ['id' => $prodSecondaryId],
                    'deposito' => ['id' => $depositoSecondaryId],
                    'operacao' => 'B',
                    'preco' => 0,
                    'custo' => 0,
                    'quantidade' => max(0, $saldoPrimary),
                    'observacoes' => 'Espelhamento Primary → Secondary',
                ]);

                if ($res['success']) {
                    $resultado['atualizados']++;
                } else {
                    $resultado['erros']++;
                    $resultado['log'][] = "SKU {$sku}: erro HTTP " . ($res['http_code'] ?? '?');
                }
            }

            $pagina++;
        } while (count($produtos) >= $limite);

        Log::info('EspelharEstoque: concluído', $resultado);

        return $resultado;
    }

    private static function buscarSaldoTotal(BlingClient $client, int $produtoId): ?int
    {
        $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $produtoId]);
        if (!$res['success'] || empty($res['body']['data'])) {
            return null;
        }

        $dados = $res['body']['data'][0] ?? null;
        if (!$dados) return null;

        $total = 0;
        foreach ($dados['depositos'] ?? [] as $dep) {
            $total += (int) ($dep['saldoFisico'] ?? 0);
        }
        return $total;
    }

    private static function getDepositoGeral(BlingClient $client): ?int
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
