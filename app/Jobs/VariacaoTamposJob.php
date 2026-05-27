<?php

namespace App\Jobs;

use App\Models\ProdutoEstoque;
use App\Models\TrocaTampoConfig;
use App\Services\Bling\BlingClient;
use App\Services\EstoqueService;
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

        $configs = TrocaTampoConfig::where('familia_tampo', '!=', '')
            ->whereNotNull('familia_tampo')
            ->where('equalizacao_ativa', true)
            ->get();

        $depositoId = self::getDepositoGeral($client);
        if (!$depositoId) {
            Log::error('VariacaoTampos: depósito Geral não encontrado');
            return ['grupos' => 0, 'atualizados' => 0, 'erros' => 1, 'sem_estoque' => 0, 'log' => ['Depósito não encontrado']];
        }

        // Agrupar por familia_tampo
        $familias = $configs->groupBy('familia_tampo');

        foreach ($familias as $familia => $membros) {
            $resultado['grupos']++;

            // Buscar saldo atual de cada SKU no Bling
            $saldosPorSku = [];
            foreach ($membros as $config) {
                $produto = $client->getProductBySku($config->sku_produto);
                if (!$produto) {
                    $resultado['log'][] = "SKU {$config->sku_produto}: não encontrado no Bling";
                    continue;
                }
                $saldo = self::buscarSaldoGeral($client, (int) $produto['id'], $depositoId);
                $saldosPorSku[$config->sku_produto] = [
                    'config'     => $config,
                    'produto_id' => (int) $produto['id'],
                    'saldo_atual' => $saldo,
                ];
            }

            // Calcular total de carcaças por cor (soma de todos os SKUs da mesma cor)
            $carcacasPorCor = [];
            foreach ($saldosPorSku as $info) {
                $cor = $info['config']->cor;
                $carcacasPorCor[$cor] = ($carcacasPorCor[$cor] ?? 0) + max(0, $info['saldo_atual']);
            }

            Log::info("VariacaoTampos: familia {$familia} - carcaças por cor", $carcacasPorCor);

            // Para cada produto: estoque = min(carcaças da cor, tampos do tipo)
            foreach ($saldosPorSku as $sku => $info) {
                $cor = $info['config']->cor;
                $totalCarcacas = $carcacasPorCor[$cor] ?? 0;

                $saldoFinal = $totalCarcacas;

                // Limitar pelo estoque do tampo correspondente
                $tampo = ProdutoEstoque::where('sku', $info['config']->sku_tampo)->where('ativo', true)->first();
                if ($tampo && $tampo->saldo < $saldoFinal) {
                    Log::info("VariacaoTampos: {$sku} limitado por tampo {$tampo->sku} (tampo={$tampo->saldo}, carcaças={$totalCarcacas})");
                    $saldoFinal = $tampo->saldo;
                }

                $saldoFinal = max(0, $saldoFinal);

                if ($info['saldo_atual'] === $saldoFinal) {
                    continue;
                }

                $obs = "Variação Tampos: {$familia}/{$cor} carcaças={$totalCarcacas} tampo=" . ($tampo ? $tampo->saldo : 'N/A');

                $res = $client->post('/estoques', [], [
                    'produto'     => ['id' => $info['produto_id']],
                    'deposito'    => ['id' => $depositoId],
                    'operacao'    => 'B',
                    'preco'       => 0,
                    'custo'       => 0,
                    'quantidade'  => $saldoFinal,
                    'observacoes' => $obs,
                ]);

                if ($res['success']) {
                    $resultado['atualizados']++;
                    $resultado['log'][] = "{$sku}: {$info['saldo_atual']} → {$saldoFinal} (carcaças={$totalCarcacas} tampo=" . ($tampo ? $tampo->saldo : 'N/A') . ")";

                    // Atualizar estoque interno (saldo_fisico = equalizado, saldo_carcaca = real)
                    EstoqueService::balanco(
                        $sku,
                        $saldoFinal,
                        'variacao_tampos',
                        "Equalização {$familia}/{$cor}",
                        null,
                        false,
                        'fisico'
                    );

                    // Guardar saldo real individual (antes da equalização)
                    ProdutoEstoque::where('sku', (string) $sku)->update(['saldo_carcaca' => max(0, $info['saldo_atual'])]);
                } else {
                    $resultado['erros']++;
                    $resultado['log'][] = "{$sku}: erro HTTP " . ($res['http_code'] ?? '?');
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

        foreach ($dados['depositos'] ?? [] as $dep) {
            $depId = (int) ($dep['deposito']['id'] ?? $dep['id'] ?? 0);
            if ($depId === $depositoGeralId) {
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
