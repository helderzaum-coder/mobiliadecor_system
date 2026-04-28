<?php

namespace App\Jobs;

use App\Models\TrocaTampoConfig;
use App\Services\Bling\BlingClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VariacaoTamposJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        private readonly string $accountKey = 'primary'
    ) {}

    public function handle(): void
    {
        $lockKey = 'variacao_tampos_running';
        if (Cache::has($lockKey)) {
            Log::warning('VariacaoTampos: já em execução, pulando');
            return;
        }
        Cache::put($lockKey, true, 600);

        try {
            $resultado = self::executar($this->accountKey);

            $admins = \App\Models\User::role('admin')->get();
            foreach ($admins as $admin) {
                \Filament\Notifications\Notification::make()
                    ->title("Variação de Tampos concluída")
                    ->body("Grupos: {$resultado['grupos']} | Atualizados: {$resultado['atualizados']} | Erros: {$resultado['erros']}")
                    ->icon($resultado['erros'] === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->iconColor($resultado['erros'] === 0 ? 'success' : 'warning')
                    ->sendToDatabase($admin);
            }
        } finally {
            Cache::forget($lockKey);
        }
    }

    public static function executar(string $accountKey = 'primary'): array
    {
        $client = new BlingClient($accountKey);
        $resultado = ['grupos' => 0, 'atualizados' => 0, 'erros' => 0, 'sem_estoque' => 0, 'log' => []];

        // Agrupar por cor + familia_tampo
        $configs = TrocaTampoConfig::where('familia_tampo', '!=', '')
            ->whereNotNull('familia_tampo')
            ->get();

        $grupos = $configs->groupBy(fn ($c) => $c->familia_tampo . '|' . $c->cor);

        $depositoId = self::getDepositoGeral($client);
        if (!$depositoId) {
            Log::error('VariacaoTampos: depósito Geral não encontrado');
            return ['grupos' => 0, 'atualizados' => 0, 'erros' => 1, 'sem_estoque' => 0, 'log' => ['Depósito não encontrado']];
        }

        foreach ($grupos as $chave => $membros) {
            if ($membros->count() < 2) continue; // Precisa de pelo menos 2 para equalizar

            $resultado['grupos']++;
            [$familia, $cor] = explode('|', $chave);

            // Buscar estoque de cada SKU
            $produtosInfo = [];

            foreach ($membros as $config) {
                $produto = $client->getProductBySku($config->sku_produto);
                if (!$produto) {
                    $resultado['log'][] = "SKU {$config->sku_produto}: não encontrado no Bling";
                    continue;
                }

                $produtoId = (int) $produto['id'];
                $skuRetornado = $produto['codigo'] ?? '?';
                $saldo = self::buscarSaldoGeral($client, $produtoId, $depositoId);

                Log::info("VariacaoTampos: leitura", [
                    'familia' => $familia,
                    'cor' => $cor,
                    'sku_config' => $config->sku_produto,
                    'sku_bling' => $skuRetornado,
                    'produto_id' => $produtoId,
                    'saldo_deposito_geral' => $saldo,
                    'deposito_id' => $depositoId,
                ]);

                $produtosInfo[] = [
                    'config' => $config,
                    'produto_id' => $produtoId,
                    'saldo_atual' => $saldo,
                ];
            }

            if (count($produtosInfo) < 2) continue;

            $saldos = array_column($produtosInfo, 'saldo_atual');
            $maior = max($saldos);
            $menor = min($saldos);

            if ($maior === $menor) {
                Log::info("VariacaoTampos: grupo {$familia}/{$cor} já equalizado em {$maior}");
                continue;
            }

            // Sempre usar o MENOR: é o único valor confiável
            // - Venda: menor reflete a venda real
            // - Primeira equalização: corrigir estoques manualmente ANTES de equalizar
            $saldoAlvo = $menor;

            Log::info("VariacaoTampos: equalizando {$familia}/{$cor}", [
                'maior' => $maior,
                'menor' => $menor,
                'diferenca' => $maior - $menor,
                'saldo_alvo' => $saldoAlvo,
                'qtd_produtos' => count($produtosInfo),
            ]);

            // Equalizar
            foreach ($produtosInfo as $info) {
                if ($info['saldo_atual'] === $saldoAlvo) {
                    continue;
                }

                $res = $client->post('/estoques', [], [
                    'produto' => ['id' => $info['produto_id']],
                    'deposito' => ['id' => $depositoId],
                    'operacao' => 'B',
                    'preco' => 0,
                    'custo' => 0,
                    'quantidade' => max(0, $saldoAlvo),
                    'observacoes' => "Variação Tampos: {$familia}/{$cor} saldo={$saldoAlvo}",
                ]);

                if ($res['success']) {
                    $resultado['atualizados']++;
                    $resultado['log'][] = "{$info['config']->sku_produto}: {$info['saldo_atual']} → {$saldoAlvo}";
                } else {
                    $resultado['erros']++;
                    $resultado['log'][] = "{$info['config']->sku_produto}: erro HTTP " . ($res['http_code'] ?? '?');
                }
            }
        }

        Log::info('VariacaoTampos: concluído', $resultado);
        return $resultado;
    }

    private static function buscarSaldoGeral(BlingClient $client, int $produtoId, int $depositoGeralId): int
    {
        $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $produtoId]);
        if (!$res['success'] || empty($res['body']['data'])) {
            return 0;
        }

        $dados = $res['body']['data'][0] ?? null;
        if (!$dados) return 0;

        // Ler APENAS o saldo do depósito Geral (mesmo onde o balanço é escrito)
        foreach ($dados['depositos'] ?? [] as $dep) {
            if ((int) ($dep['id'] ?? 0) === $depositoGeralId) {
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
