<?php

namespace App\Console\Commands;

use App\Services\Bling\BlingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BlingSincronizarEstoqueInicial extends Command
{
    protected $signature = 'bling:sync-estoque-inicial
                            {origem=primary : Conta de origem (primary|secondary)}
                            {--limit=0 : Limitar quantidade de SKUs (0 = todos)}
                            {--dry-run : Simula sem atualizar}';

    protected $description = 'Sincroniza estoque completo de uma conta Bling para a outra';

    private ?int $depositoIdCache = null;

    public function handle(): int
    {
        $origem  = $this->argument('origem');
        $destino = $origem === 'primary' ? 'secondary' : 'primary';
        $limit   = (int) $this->option('limit');
        $dryRun  = $this->option('dry-run');

        if (!in_array($origem, ['primary', 'secondary'])) {
            $this->error('Conta inválida. Use primary ou secondary.');
            return 1;
        }

        $this->info("Sincronização inicial: {$origem} → {$destino}");
        if ($dryRun) $this->warn('MODO DRY-RUN: nenhuma alteração será feita');

        $clientOrigem  = new BlingClient($origem);
        $clientDestino = new BlingClient($destino);

        // Coletar SKUs únicos: componentes de kits + produtos simples/variações
        $this->info('Coletando SKUs da conta de origem...');
        $skus = $this->coletarSkusParaSincronizar($clientOrigem, $limit);

        if (empty($skus)) {
            $this->error('Nenhum SKU encontrado para sincronizar.');
            return 1;
        }

        $this->info('Total de SKUs para sincronizar: ' . count($skus));

        $ok = 0; $pulados = 0; $erros = 0;
        $bar = $this->output->createProgressBar(count($skus));
        $bar->start();

        foreach ($skus as $sku => $saldoOrigem) {
            // Busca no destino aceitando qualquer formato (S, V, E, C)
            $prodDestino = $this->buscarProdutoNoDestino($clientDestino, $sku);

            if (!$prodDestino) {
                $pulados++;
                $bar->advance();
                usleep(200000);
                continue;
            }

            $prodDestinoId = (int) $prodDestino['id'];
            $saldoDestino  = $this->buscarSaldoDisponivel($clientDestino, $prodDestinoId);
            if ($saldoDestino === null) {
                $saldoDestino = (int) ($prodDestino['estoque']['saldoFisicoTotal']
                                ?? $prodDestino['estoque']['saldoVirtualTotal'] ?? 0);
            }

            // Já está igual
            if ($saldoDestino === $saldoOrigem) {
                $pulados++;
                $bar->advance();
                continue;
            }

            if (!$dryRun) {
                Cache::put("bling_sync_loop_{$destino}_{$prodDestinoId}", true, now()->addSeconds(60));

                $res = $clientDestino->post('/estoques', [], [
                    'produto'     => ['id' => $prodDestinoId],
                    'deposito'    => ['id' => $this->getDepositoId($clientDestino)],
                    'operacao'    => 'B',
                    'preco'       => 0,
                    'custo'       => 0,
                    'quantidade'  => $saldoOrigem,
                    'observacoes' => 'Sincronização inicial via sistema',
                ]);

                if ($res['success']) {
                    $ok++;
                } else {
                    $this->newLine();
                    $this->line("  <error>SKU {$sku}: erro HTTP {$res['http_code']}</error>");
                    $erros++;
                }
            } else {
                $this->newLine();
                $this->line("  [DRY] SKU {$sku}: {$saldoDestino} → {$saldoOrigem}");
                $ok++;
            }

            $bar->advance();
            usleep(350000);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Concluído: {$ok} atualizados, {$pulados} pulados, {$erros} erros.");

        return 0;
    }

    /**
     * Coleta SKUs para sincronizar:
     * - Simples/variação: sincroniza direto
     * - Kit (E/C): extrai componentes e sincroniza cada um
     */
    private function coletarSkusParaSincronizar(BlingClient $client, int $limit): array
    {
        $skus   = [];
        $pagina = 1;

        do {
            $res   = $client->get('/produtos', ['pagina' => $pagina, 'limite' => 100, 'situacao' => 'A']);
            $dados = $res['body']['data'] ?? [];

            foreach ($dados as $produto) {
                $formato = strtoupper($produto['formato'] ?? 'S');
                $sku     = $produto['codigo'] ?? null;

                if ($formato === 'E' || $formato === 'C') {
                    // Kit: buscar componentes e sincronizar cada um
                    $detalhe     = $client->getProductById((int) $produto['id']);
                    $componentes = $detalhe['estrutura']['componentes'] ?? [];

                    foreach ($componentes as $comp) {
                        $compId = $comp['produto']['id'] ?? null;
                        if (!$compId) continue;

                        $compDetalhe = $client->getProductById((int) $compId);
                        $compSku     = $compDetalhe['codigo'] ?? null;
                        if (!$compSku || isset($skus[$compSku])) continue;

                        $saldo = $this->buscarSaldoDisponivel($client, (int) $compId);
                        if ($saldo === null) {
                            $saldo = (int) ($compDetalhe['estoque']['saldoFisicoTotal']
                                        ?? $compDetalhe['estoque']['saldoVirtualTotal'] ?? 0);
                        }
                        $skus[$compSku] = $saldo;
                        usleep(200000);
                    }
                } elseif ($sku && !isset($skus[$sku])) {
                    // Simples ou variação
                    $saldo = $this->buscarSaldoDisponivel($client, (int) $produto['id']);
                    if ($saldo === null) {
                        $detalhe = $client->getProductById((int) $produto['id']);
                        $saldo   = (int) ($detalhe['estoque']['saldoFisicoTotal']
                                    ?? $detalhe['estoque']['saldoVirtualTotal'] ?? 0);
                    }
                    $skus[$sku] = $saldo;
                    usleep(200000);
                }

                if ($limit > 0 && count($skus) >= $limit) break 2;
            }

            $pagina++;
        } while (count($dados) >= 100);

        return $skus;
    }

    /**
     * Busca produto no destino pelo SKU — aceita qualquer formato,
     * incluindo variações dentro de produtos pai.
     */
    private function buscarProdutoNoDestino(BlingClient $client, string $sku): ?array
    {
        $res = $client->get('/produtos', ['codigo' => $sku, 'limite' => 100]);

        if (!$res['success'] || empty($res['body']['data'])) return null;

        foreach ($res['body']['data'] as $p) {
            if (($p['codigo'] ?? '') === $sku) {
                return $client->getProductById((int) $p['id']) ?? $p;
            }

            // Verificar variações dentro do produto pai
            foreach ($p['variacoes'] ?? [] as $v) {
                if (($v['codigo'] ?? '') === $sku) {
                    return $client->getProductById((int) $v['id']) ?? $v;
                }
            }
        }

        return null;
    }

    private function getDepositoId(BlingClient $client): int
    {
        if ($this->depositoIdCache) return $this->depositoIdCache;
        $res = $client->get('/depositos', ['limite' => 1]);
        $this->depositoIdCache = (int) ($res['body']['data'][0]['id'] ?? 1);
        return $this->depositoIdCache;
    }
}
