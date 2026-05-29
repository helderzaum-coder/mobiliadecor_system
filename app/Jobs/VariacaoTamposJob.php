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

        Log::info("VariacaoTampos: encontrados {$configs->count()} configs ativos. Famílias: " . $configs->pluck('familia_tampo')->unique()->implode(', '));
        Log::info("VariacaoTampos: SKUs processados: " . $configs->pluck('sku_produto')->implode(', '));

        $depositoId = self::getDepositoGeral($client);
        if (!$depositoId) {
            Log::error('VariacaoTampos: depósito Geral não encontrado');
            return ['grupos' => 0, 'atualizados' => 0, 'erros' => 1, 'sem_estoque' => 0, 'log' => ['Depósito não encontrado']];
        }

        // Agrupar por familia_tampo
        $familias = $configs->groupBy('familia_tampo');

        foreach ($familias as $familia => $membros) {
            $resultado['grupos']++;

            // PASSO 1: Calcular total de carcaças por GRUPO+COR usando saldo_carcaca do BANCO LOCAL.
            // Isso é feito ANTES de qualquer chamada ao Bling, garantindo que falhas de API
            // não afetem a soma de carcaças (fonte confiável = banco interno).
            // IMPORTANTE: carcaças NÃO são compartilhadas entre grupos diferentes (ex: Elisa e Jade
            // compartilham só o TAMPO, não a carcaça). Por isso agrupamos por grupo+cor, não família+cor.
            $carcacasPorGrupoCor = [];
            foreach ($membros as $config) {
                $produtoInterno = ProdutoEstoque::where('sku', $config->sku_produto)->where('ativo', true)->first();
                $saldoCarcaca = ($produtoInterno && $produtoInterno->saldo_carcaca !== null)
                    ? (int) $produtoInterno->saldo_carcaca
                    : 0;
                $chave = $config->grupo . '|' . $config->cor;
                $carcacasPorGrupoCor[$chave] = ($carcacasPorGrupoCor[$chave] ?? 0) + max(0, $saldoCarcaca);
            }

            Log::info("VariacaoTampos: familia {$familia} - carcaças por grupo+cor (do banco local)", $carcacasPorGrupoCor);

            // PASSO 2: Buscar saldo atual de cada SKU no Bling para aplicar o balanço.
            $saldosPorSku = [];
            foreach ($membros as $config) {
                $produto = $client->getProductBySku($config->sku_produto);
                if (!$produto) {
                    $resultado['log'][] = "SKU {$config->sku_produto}: não encontrado no Bling (pulado)";
                    Log::warning("VariacaoTampos: SKU {$config->sku_produto} não encontrado no Bling — pulado (carcaças já contabilizadas)");
                    continue;
                }
                $saldoBling = self::buscarSaldoGeral($client, (int) $produto['id'], $depositoId);

                $produtoInterno = ProdutoEstoque::where('sku', $config->sku_produto)->where('ativo', true)->first();
                $saldoCarcaca = ($produtoInterno && $produtoInterno->saldo_carcaca !== null)
                    ? (int) $produtoInterno->saldo_carcaca
                    : 0;

                Log::info("VariacaoTampos: SKU {$config->sku_produto} — saldoBling={$saldoBling}, saldo_carcaca={$saldoCarcaca}" . ($produtoInterno && $produtoInterno->saldo_carcaca === null ? ' (null→0)' : ''));

                $saldosPorSku[$config->sku_produto] = [
                    'config'      => $config,
                    'produto_id'  => (int) $produto['id'],
                    'saldo_atual' => $saldoBling,
                    'saldo_carcaca' => $saldoCarcaca,
                ];
            }

            Log::info("VariacaoTampos: familia {$familia} - saldos detalhados", collect($saldosPorSku)->map(fn($i) => [
                'sku' => $i['config']->sku_produto,
                'grupo' => $i['config']->grupo,
                'cor' => $i['config']->cor,
                'saldo_bling' => $i['saldo_atual'],
                'saldo_carcaca' => $i['saldo_carcaca'],
                'sku_tampo' => $i['config']->sku_tampo,
            ])->values()->toArray());

            // Para cada produto: estoque = min(carcaças do grupo+cor, tampos do tipo)
            foreach ($saldosPorSku as $sku => $info) {
                $chaveGrupoCor = $info['config']->grupo . '|' . $info['config']->cor;
                $totalCarcacas = $carcacasPorGrupoCor[$chaveGrupoCor] ?? 0;
                $produtoInterno = ProdutoEstoque::where('sku', (string) $sku)->where('ativo', true)->first();

                $saldoFinal = $totalCarcacas;

                // Limitar pelo estoque do tampo correspondente (usar saldo_fisico do tampo, não saldo total)
                $tampo = ProdutoEstoque::where('sku', $info['config']->sku_tampo)->where('ativo', true)->first();
                if ($tampo && $tampo->saldo_fisico < $saldoFinal) {
                    Log::info("VariacaoTampos: {$sku} limitado por tampo {$tampo->sku} (tampo_fisico={$tampo->saldo_fisico}, carcaças={$totalCarcacas})");
                    $saldoFinal = $tampo->saldo_fisico;
                }

                $saldoFinal = max(0, (int) $saldoFinal);

                Log::info("VariacaoTampos: {$sku} — saldo_atual={$info['saldo_atual']}, saldoFinal={$saldoFinal}, tampo=" . ($tampo ? $tampo->saldo_fisico : 'N/A'));

                // Se o Bling já está com o saldo correto, ainda assim garantir que o saldo local
                // (saldo_fisico) esteja sincronizado. Sem isso, Bling e banco interno podem divergir.
                if ((int) $info['saldo_atual'] == $saldoFinal) {
                    if ($produtoInterno && (int) $produtoInterno->saldo_fisico !== $saldoFinal) {
                        Log::info("VariacaoTampos: {$sku} — Bling OK ({$saldoFinal}) mas local divergente ({$produtoInterno->saldo_fisico}). Sincronizando local sem reenviar ao Bling.");
                        EstoqueService::balanco(
                            $sku,
                            $saldoFinal,
                            'variacao_tampos',
                            "Sincronização local {$info['config']->grupo}/{$info['config']->cor}",
                            null,
                            false, // não reenviar ao Bling (já está correto)
                            'fisico'
                        );
                        $resultado['atualizados']++;
                        $resultado['log'][] = "{$sku}: local sincronizado para {$saldoFinal} (Bling já estava correto)";
                    } else {
                        Log::info("VariacaoTampos: {$sku} — sem alteração (atual={$info['saldo_atual']} == final={$saldoFinal})");
                    }
                    continue;
                }

                $grupoCorLabel = $info['config']->grupo . '/' . $info['config']->cor;
                $obs = "Variação Tampos: {$grupoCorLabel} carcaças={$totalCarcacas} tampo=" . ($tampo ? $tampo->saldo_fisico : 'N/A');

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
                    $resultado['log'][] = "{$sku}: {$info['saldo_atual']} → {$saldoFinal} (carcaças={$totalCarcacas} tampo=" . ($tampo ? $tampo->saldo_fisico : 'N/A') . ")";

                    // Atualizar estoque interno (saldo_fisico = equalizado, saldo_carcaca = real)
                    EstoqueService::balanco(
                        $sku,
                        $saldoFinal,
                        'variacao_tampos',
                        "Equalização {$grupoCorLabel}",
                        null,
                        false,
                        'fisico'
                    );

                    // Guardar saldo real individual apenas se nunca foi definido (null)
                    // NUNCA sobrescrever com saldo equalizado — saldo_carcaca deve refletir carcaças reais
                    if ($produtoInterno && $produtoInterno->saldo_carcaca === null) {
                        // Primeira vez: marcar como 0 — o admin deve informar manualmente via botão "Carcaças"
                        Log::warning("VariacaoTampos: {$sku} saldo_carcaca era null, definido como 0. Ajuste manualmente se necessário.");
                        ProdutoEstoque::where('sku', (string) $sku)->update(['saldo_carcaca' => 0]);
                    }
                } else {
                    $resultado['erros']++;
                    $resultado['log'][] = "{$sku}: erro HTTP " . ($res['http_code'] ?? '?') . " — " . json_encode($res['body'] ?? []);
                    Log::error("VariacaoTampos: FALHA ao atualizar {$sku} para {$saldoFinal}", [
                        'http_code' => $res['http_code'] ?? null,
                        'body' => $res['body'] ?? null,
                    ]);
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
