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

class ImportarProdutosBlingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function handle(): void
    {
        $lockKey = 'importar_produtos_bling_running';
        if (Cache::has($lockKey)) {
            Log::warning('ImportarProdutosBling: já em execução');
            return;
        }
        Cache::put($lockKey, true, 900);

        try {
            $resultado = self::executar();

            $admins = \App\Models\User::role('admin')->get();
            foreach ($admins as $admin) {
                \Filament\Notifications\Notification::make()
                    ->title("Importação de produtos concluída")
                    ->body("Criados: {$resultado['criados']} | Atualizados: {$resultado['atualizados']} | Kits: {$resultado['kits']} | Erros: {$resultado['erros']}")
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
        $client = new BlingClient('primary');
        $resultado = ['criados' => 0, 'atualizados' => 0, 'kits' => 0, 'erros' => 0];

        $depositoId = self::getDepositoGeral($client);

        $pagina = 1;
        $limite = 100;

        do {
            $res = $client->get('/produtos', ['pagina' => $pagina, 'limite' => $limite]);
            if (!$res['success']) {
                $resultado['erros']++;
                break;
            }

            $produtos = $res['body']['data'] ?? [];
            if (empty($produtos)) break;

            foreach ($produtos as $produto) {
                $sku = $produto['codigo'] ?? '';
                if (empty($sku)) continue;

                $nome = $produto['nome'] ?? $sku;
                $formato = strtoupper($produto['formato'] ?? 'S');

                $produtoEstoque = ProdutoEstoque::where('sku', $sku)->first();

                if ($produtoEstoque) {
                    // Já existe: atualiza apenas nome e formato, NÃO mexe no saldo
                    $produtoEstoque->update(['nome' => $nome, 'formato' => $formato]);
                    $resultado['atualizados']++;
                } else {
                    // Novo: buscar saldo do Bling e colocar como virtual
                    $saldo = 0;
                    if ($depositoId && !in_array($formato, ['E', 'C'])) {
                        $saldo = self::buscarSaldo($client, (int) $produto['id'], $depositoId);
                    }

                    $produtoEstoque = ProdutoEstoque::create([
                        'sku' => $sku,
                        'nome' => $nome,
                        'formato' => $formato,
                        'saldo_virtual' => $saldo,
                        'saldo_fisico' => 0,
                    ]);
                    $resultado['criados']++;
                }

                // Se é kit, importar componentes
                if (in_array($formato, ['E', 'C'])) {
                    self::importarComponentes($client, $produto, $produtoEstoque);
                    $resultado['kits']++;
                }
            }

            $pagina++;
        } while (count($produtos) >= $limite);

        Log::info('ImportarProdutosBling: concluído', $resultado);
        return $resultado;
    }

    private static function importarComponentes(BlingClient $client, array $produtoBling, ProdutoEstoque $kit): void
    {
        $detalhe = $client->getProductById((int) $produtoBling['id']);
        $componentes = $detalhe['estrutura']['componentes'] ?? [];

        if (empty($componentes)) return;

        $syncData = [];
        foreach ($componentes as $comp) {
            $compId = $comp['produto']['id'] ?? null;
            if (!$compId) continue;

            $compDetalhe = $client->getProductById((int) $compId);
            $compSku = $compDetalhe['codigo'] ?? null;
            if (!$compSku) continue;

            // Garantir que o componente existe no cadastro
            $compEstoque = ProdutoEstoque::firstOrCreate(
                ['sku' => $compSku],
                ['nome' => $compDetalhe['nome'] ?? $compSku, 'formato' => 'S']
            );

            $syncData[$compEstoque->id] = ['quantidade' => (int) ($comp['quantidade'] ?? 1)];
        }

        if (!empty($syncData)) {
            $kit->componentes()->sync($syncData);
        }
    }

    private static function buscarSaldo(BlingClient $client, int $produtoId, int $depositoId): int
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
