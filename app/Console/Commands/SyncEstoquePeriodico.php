<?php

namespace App\Console\Commands;

use App\Services\Bling\BlingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncEstoquePeriodico extends Command
{
    protected $signature = 'bling:sync-estoque-periodico
                           {--intervalo=30 : Minutos para trás para buscar mudanças}
                           {--dry-run : Simular execução sem alterar estoque}';
    
    protected $description = 'Sincroniza estoque entre contas (seguro - não altera dados cadastrais)';

    const PROCESSED_ORDERS_CACHE = 'sync_processed_orders_';
    const MOVIMENTACAO_VEIO_SECONDARY = 'movimentacao_veio_secondary_';
    const MOVIMENTACAO_VEIO_PRIMARY = 'movimentacao_veio_primary_';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 MODO DRY RUN - Nenhuma alteração será feita no estoque');
        }
        
        $this->info('🔄 Iniciando sincronização periódica de estoque...');
        
        $lock = Cache::lock('sync-estoque-periodico', 300);
        if (!$lock->get()) {
            $this->warn('⚠️ Sincronização já em execução. Pulando...');
            return 0;
        }
        
        try {
            $intervaloMinutos = (int) $this->option('intervalo');
            $dataInicio = now()->subMinutes($intervaloMinutos)->toDateTimeString();
            
            $this->line("📅 Período: {$dataInicio}");
            
            // ============================================
            // PASSO 1: SECONDARY → PRIMARY
            // ============================================
            $this->line("\n🔍 PASSO 1: Secondary → Primary");
            $this->sincronizarSecondaryParaPrimary($dataInicio, $dryRun);
            
            // ============================================
            // PASSO 2: PRIMARY → SECONDARY
            // ============================================
            $this->line("\n🔍 PASSO 2: Primary → Secondary");
            $this->sincronizarPrimaryParaSecondary($dataInicio, $dryRun);
            
            $this->info("\n✅ Sincronização concluída!");
            
            $lock->release();
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Erro: ' . $e->getMessage());
            Log::error('SyncEstoquePeriodico falhou', ['error' => $e->getMessage()]);
            $lock->release();
            return 1;
        }
    }
    
    /**
     * Sincroniza vendas da Secondary → Primary
     */
    private function sincronizarSecondaryParaPrimary(string $dataInicio, bool $dryRun): void
    {
        $secondaryClient = new BlingClient('secondary');
        $primaryClient = new BlingClient('primary');
        
        $pedidos = $this->buscarPedidosAprovados($secondaryClient, $dataInicio);
        
        if (empty($pedidos)) {
            $this->line('✅ Nenhuma venda na Secondary');
            return;
        }
        
        // Agrupar por SKU
        $vendasPorSku = [];
        foreach ($pedidos as $pedido) {
            foreach ($pedido['itens'] as $sku => $quantidade) {
                $vendasPorSku[$sku] = ($vendasPorSku[$sku] ?? 0) + $quantidade;
            }
        }
        
        $this->line("📦 " . count($vendasPorSku) . " SKUs vendidos na Secondary");
        
        if ($dryRun) {
            foreach ($vendasPorSku as $sku => $qtd) {
                $this->line("   - SKU {$sku}: {$qtd} unidade(s)");
            }
            $this->warn("   [DRY RUN] Nenhuma baixa executada");
            return;
        }
        
        foreach ($vendasPorSku as $sku => $quantidade) {
            $this->line("\n📉 SKU {$sku}: baixar {$quantidade} unidade(s) na Primary");
            
            $produto = $primaryClient->getProductBySku($sku);
            if (!$produto) {
                $this->warn("   ⚠️ SKU não encontrado na Primary");
                continue;
            }
            
            $depositoId = $this->getDepositoGeralId($primaryClient);
            if (!$depositoId) {
                $this->error("   ❌ Depósito não encontrado");
                continue;
            }
            
            $resultado = $this->movimentarEstoque(
                $primaryClient,
                $produto['id'],
                $depositoId,
                $quantidade,
                'S',  // Saída
                "Venda Secondary - {$quantidade} unidade(s)"
            );
            
            if ($resultado['success']) {
                $this->line("   ✅ Baixa realizada");
                Cache::put(self::MOVIMENTACAO_VEIO_SECONDARY . $produto['id'], true, now()->addMinutes(30));
            } else {
                $this->error("   ❌ Falha: " . ($resultado['body']['error'] ?? 'Erro'));
            }
            
            usleep(500000);
        }
    }
    
    /**
     * Sincroniza vendas da Primary → Secondary
     */
    private function sincronizarPrimaryParaSecondary(string $dataInicio, bool $dryRun): void
    {
        $primaryClient = new BlingClient('primary');
        $secondaryClient = new BlingClient('secondary');
        
        $pedidos = $this->buscarPedidosAprovados($primaryClient, $dataInicio);
        
        if (empty($pedidos)) {
            $this->line('✅ Nenhuma venda na Primary');
            return;
        }
        
        // Agrupar por SKU
        $vendasPorSku = [];
        foreach ($pedidos as $pedido) {
            foreach ($pedido['itens'] as $sku => $quantidade) {
                $vendasPorSku[$sku] = ($vendasPorSku[$sku] ?? 0) + $quantidade;
            }
        }
        
        $this->line("📦 " . count($vendasPorSku) . " SKUs vendidos na Primary");
        
        if ($dryRun) {
            foreach ($vendasPorSku as $sku => $qtd) {
                $this->line("   - SKU {$sku}: {$qtd} unidade(s)");
            }
            $this->warn("   [DRY RUN] Nenhuma baixa executada");
            return;
        }
        
        foreach ($vendasPorSku as $sku => $quantidade) {
            // Verificar se esta movimentação veio da Secondary (evitar loop)
            $produtoPrimary = $primaryClient->getProductBySku($sku);
            if ($produtoPrimary && Cache::has(self::MOVIMENTACAO_VEIO_SECONDARY . $produtoPrimary['id'])) {
                $this->line("\n⏭️ SKU {$sku}: ignorado (veio da Secondary)");
                Cache::forget(self::MOVIMENTACAO_VEIO_SECONDARY . $produtoPrimary['id']);
                continue;
            }
            
            $this->line("\n📉 SKU {$sku}: baixar {$quantidade} unidade(s) na Secondary");
            
            $produto = $secondaryClient->getProductBySku($sku);
            if (!$produto) {
                $this->warn("   ⚠️ SKU não encontrado na Secondary");
                continue;
            }
            
            $depositoId = $this->getDepositoGeralId($secondaryClient);
            if (!$depositoId) {
                $this->error("   ❌ Depósito não encontrado");
                continue;
            }
            
            $resultado = $this->movimentarEstoque(
                $secondaryClient,
                $produto['id'],
                $depositoId,
                $quantidade,
                'S',  // Saída
                "Venda Primary - {$quantidade} unidade(s)"
            );
            
            if ($resultado['success']) {
                $this->line("   ✅ Baixa realizada");
                Cache::put(self::MOVIMENTACAO_VEIO_PRIMARY . $produto['id'], true, now()->addMinutes(30));
            } else {
                $this->error("   ❌ Falha: " . ($resultado['body']['error'] ?? 'Erro'));
            }
            
            usleep(500000);
        }
    }
    
    /**
     * Movimenta estoque (SEGURANÇA: NUNCA usa operacao 'B')
     */
    private function movimentarEstoque(BlingClient $client, int $produtoId, int $depositoId, int $quantidade, string $tipo, string $observacao): array
    {
        $payload = [
            'produto' => ['id' => $produtoId],
            'deposito' => ['id' => $depositoId],
            'tipo' => $tipo,  // 'E' ou 'S'
            'quantidade' => $quantidade,
            'observacao' => $observacao,
        ];
        
        $response = $client->post('/estoques/movimentacoes', [], $payload);
        
        // Fallback para API legada (NUNCA usa 'B')
        if (!$response['success'] && ($response['http_code'] == 404)) {
            $payloadLegado = [
                'produto' => ['id' => $produtoId],
                'deposito' => ['id' => $depositoId],
                'operacao' => $tipo,  // 'E' ou 'S' - NUNCA 'B'
                'quantidade' => $quantidade,
                'preco' => 0,
                'custo' => 0,
                'observacoes' => $observacao,
            ];
            $response = $client->post('/estoques', [], $payloadLegado);
        }
        
        Log::info("Movimentação estoque", [
            'produto_id' => $produtoId,
            'quantidade' => $quantidade,
            'tipo' => $tipo,
            'success' => $response['success'],
        ]);
        
        return $response;
    }
    
    /**
     * Busca pedidos aprovados no período
     */
    private function buscarPedidosAprovados(BlingClient $client, string $dataInicio): array
    {
        $pedidos = [];
        $pagina = 1;
        $limite = 100;
        
        do {
            $response = $client->getPedidos([
                'dataInicial' => $dataInicio,
                'situacao' => 'aprovado',
                'pagina' => $pagina,
                'limite' => $limite,
            ]);
            
            if (!$response['success']) {
                break;
            }
            
            $lista = $response['body']['data'] ?? [];
            
            foreach ($lista as $resumo) {
                $blingId = $resumo['id'];
                $cacheKey = self::PROCESSED_ORDERS_CACHE . $blingId;
                
                if (Cache::has($cacheKey)) {
                    continue;
                }
                
                $detalhe = $client->getPedido($blingId);
                if ($detalhe['success']) {
                    $pedido = $detalhe['body']['data'] ?? [];
                    $itens = [];
                    
                    foreach ($pedido['itens'] ?? [] as $item) {
                        $sku = $item['codigo'] ?? '';
                        if ($sku) {
                            $itens[$sku] = ($itens[$sku] ?? 0) + (int) ($item['quantidade'] ?? 1);
                        }
                    }
                    
                    if (!empty($itens)) {
                        $pedidos[] = [
                            'bling_id' => $blingId,
                            'numero' => $pedido['numero'] ?? $blingId,
                            'itens' => $itens,
                        ];
                    }
                    
                    Cache::put($cacheKey, true, now()->addHours(24));
                }
                
                usleep(200000);
            }
            
            $pagina++;
        } while (count($lista) >= $limite);
        
        return $pedidos;
    }
    
    /**
     * Obtém ID do depósito Geral
     */
    private function getDepositoGeralId(BlingClient $client): ?int
    {
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('accountKey');
        $property->setAccessible(true);
        $accountKey = $property->getValue($client);
        
        $cacheKey = "deposito_geral_id_{$accountKey}";
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($client) {
            $response = $client->get('/depositos', ['limite' => 100]);
            if (!$response['success']) {
                return null;
            }
            
            foreach ($response['body']['data'] ?? [] as $deposito) {
                $nome = strtolower($deposito['descricao'] ?? '');
                if (str_contains($nome, 'geral') || str_contains($nome, 'principal')) {
                    return (int) $deposito['id'];
                }
            }
            
            return isset($response['body']['data'][0]) ? (int) $response['body']['data'][0]['id'] : null;
        });
    }
}