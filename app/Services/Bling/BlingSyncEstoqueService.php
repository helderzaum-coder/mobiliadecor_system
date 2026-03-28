<?php

namespace App\Services\Bling;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  BlingSyncEstoqueService — Mirroring Automático de Estoque         ║
 * ║                                                                    ║
 * ║  Garante que o estoque das duas contas Bling seja idêntico,        ║
 * ║  sincronizando depósito a depósito (Geral, Virtual, etc).          ║
 * ║                                                                    ║
 * ║  Estratégia: Absolute Mirroring (sempre busca o saldo REAL na      ║
 * ║  origem e espelha no destino, evitando drift de quantidades).      ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */
class BlingSyncEstoqueService
{
    private BlingClient $origem;
    private BlingClient $destino;
    private string $origemKey;
    private string $destinoKey;

    public function __construct(string $origemKey)
    {
        $this->origemKey  = $origemKey;
        $this->destinoKey = $origemKey === 'primary' ? 'secondary' : 'primary';
        $this->origem     = new BlingClient($origemKey);
        $this->destino    = new BlingClient($this->destinoKey);
    }

    /**
     * Espelha o saldo de um produto da conta origem para a destino (por depósitos).
     */
    public function espelharEstoque(int $produtoIdOrigem, float $saldoWebhook): array
    {
        // Anti-loop protection: se este ID acabou de ser atualizado pelo sync, ignorar
        $cacheKey = "bling_sync_loop_{$this->origemKey}_{$produtoIdOrigem}";
        if (Cache::has($cacheKey)) {
            return ['success' => true, 'log' => ["Sync ignorado (anti-loop) para produto #{$produtoIdOrigem}"]];
        }

        $log = [];

        // 1. Buscar detalhes do produto na ORIGEM para identificar o SKU e formato
        $prodOrigem = $this->origem->getProductById($produtoIdOrigem);

        if (!$prodOrigem) {
            return ['success' => false, 'log' => ["Produto #{$produtoIdOrigem} não encontrado na origem"]];
        }

        $sku     = $prodOrigem['codigo'] ?? null;
        $formato = strtoupper($prodOrigem['formato'] ?? 'S');

        if (!$sku) {
            return ['success' => false, 'log' => ["Produto #{$produtoIdOrigem} não possui SKU — pulando"]];
        }

        $log[] = "--- Espelhando SKU: {$sku} ({$this->origemKey} → {$this->destinoKey}) ---";

        // 2. Buscar produto no DESTINO
        $prodDestino = $this->buscarProdutoCompleto($this->destino, $sku, true);

        if (!$prodDestino) {
            $log[] = "SKU {$sku} não encontrado no destino — ignorando";
            return ['success' => true, 'log' => $log];
        }

        $produtoIdDestino = (int) $prodDestino['id'];

        // 3. Marcar anti-loop no DESTINO para evitar gatilho imediato de volta
        Cache::put("bling_sync_loop_{$this->destinoKey}_{$produtoIdDestino}", true, now()->addMinutes(10));

        // CASO A: Kits (Formato E/C) — Usam saldo consolidado (não têm depósitos físicos próprios no Bling)
        if ($formato === 'E' || $formato === 'C') {
            $saldoTotal = (int) ($prodOrigem['estoque']['saldoVirtualTotal'] ?? 0);
            $log[] = "Produto é KIT: espelhando saldo consolidado {$saldoTotal}";
            $res = $this->atualizarEstoque($produtoIdDestino, $saldoTotal, null);
            return ['success' => $res['success'], 'log' => array_merge($log, $res['success']?['✓ Sucesso']:['✗ Erro'])];
        }

        // CASO B: Produtos Simples ou Variações — Mirroring Depósito a Depósito
        $saldosOrigem = $this->buscarSaldosPorDeposito($this->origem, $produtoIdOrigem);

        if (!$saldosOrigem) {
            // Fallback: se não conseguir ler por depósito, usa o consolidado
            $saldoTotal = (int) ($prodOrigem['estoque']['saldoVirtualTotal'] ?? 0);
            $log[] = "Falha ao segmentar depósitos: usando saldo consolidado {$saldoTotal}";
            $res = $this->atualizarEstoque($produtoIdDestino, $saldoTotal, null);
            return ['success' => $res['success'], 'log' => array_merge($log, $res['success']?['✓ Sucesso']:['✗ Erro'])];
        }

        $success = true;
        foreach ($saldosOrigem as $nomeDeposito => $quantidade) {
            $log[] = "Saldo '{$nomeDeposito}': {$quantidade}";
            
            $depDestinoId = $this->getDepositoIdPorNome($this->destino, $nomeDeposito);
            
            if ($depDestinoId) {
                $res = $this->atualizarEstoque($produtoIdDestino, $quantidade, $depDestinoId);
                if ($res['success']) {
                    $log[] = "  ✓ atualizado no destino (depósito ID {$depDestinoId})";
                } else {
                    $log[] = "  ✗ falha ao atualizar depósito '{$nomeDeposito}' (HTTP {$res['http_code']})";
                    $success = false;
                }
            } else {
                $log[] = "  ! depósito '{$nomeDeposito}' não existe no destino — ignorando este saldo";
            }
        }

        Log::info("BlingSyncEstoque: Mirroring finalizado", [
            'sku' => $sku,
            'origem' => $this->origemKey,
            'log'    => $log
        ]);

        return ['success' => $success, 'log' => $log];
    }

    /**
     * Processa um pedido recebido via webhook.
     */
    public function processarPedido(int $pedidoId): array
    {
        $res = $this->origem->getPedido($pedidoId);
        if (!$res['success'] || empty($res['body']['data'])) {
            return ['success' => false, 'log' => ["Pedido #{$pedidoId} não encontrado na origem"]];
        }

        $itens = $res['body']['data']['itens'] ?? [];
        $log   = ["Pedido #{$pedidoId} ({$this->origemKey}): " . count($itens) . " item(ns)"];

        foreach ($itens as $item) {
            $sku = $item['codigo'] ?? null;
            if (!$sku) continue;

            // Para cada item vendido, forçamos o espelhamento absoluto para regularizar os depósitos
            $prodOrigem = $this->buscarProdutoCompleto($this->origem, $sku, true);
            if ($prodOrigem) {
                $resultado = $this->espelharEstoque((int) $prodOrigem['id'], 0);
                $log = array_merge($log, $resultado['log']);
            }
        }

        return ['success' => true, 'log' => $log];
    }

    /**
     * Busca saldos por depósito filtrando apenas os relevantes (Geral, Virtual).
     */
    private function buscarSaldosPorDeposito(BlingClient $client, int $produtoId): ?array
    {
        $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $produtoId]);
        if (!$res['success'] || empty($res['body']['data'])) return null;

        $dados = $res['body']['data'][0] ?? null;
        
        $depositosRes = $client->get('/depositos', ['limite' => 100]);
        if (!$depositosRes['success']) return null;

        $depNomes = [];
        foreach ($depositosRes['body']['data'] ?? [] as $d) {
            $depNomes[$d['id']] = $d['descricao'] ?? '';
        }

        $saldos = [];
        foreach ($dados['depositos'] ?? [] as $item) {
            $nome = $depNomes[$item['id']] ?? null;
            if ($nome) {
                $n = strtolower($nome);
                if (str_contains($n, 'geral') || str_contains($n, 'virtual') || str_contains($n, 'virtal')) {
                    $saldos[$nome] = (int) ($item['saldoFisico'] ?? 0);
                }
            }
        }

        return !empty($saldos) ? $saldos : null;
    }

    /**
     * Mapeia nome de depósito para ID de forma dinâmica e cacheada.
     */
    private function getDepositoIdPorNome(BlingClient $client, string $nome): ?int
    {
        // Tenta descobrir a chave da conta (primary/secondary) dinamicamente
        $accountKey = ($client === $this->origem) ? $this->origemKey : $this->destinoKey;
        $cacheKey   = "bling_dep_id_{$accountKey}_" . str_replace(' ', '_', $nome);

        $id = Cache::get($cacheKey);
        if ($id) return (int) $id;

        $res = $client->get('/depositos', ['limite' => 100]);
        if (!$res['success']) return null;

        $target = strtolower(trim($nome));
        foreach ($res['body']['data'] ?? [] as $d) {
            if (strtolower(trim($d['descricao'] ?? '')) === $target) {
                Cache::put($cacheKey, $d['id'], now()->addDays(1));
                return (int) $d['id'];
            }
        }
        return null;
    }

    /**
     * Busca produto completo pelo SKU.
     */
    private function buscarProdutoCompleto(BlingClient $client, string $sku, bool $allowKit = false): ?array
    {
        $res = $client->get('/produtos', ['codigo' => $sku, 'limite' => 10]);
        if (!$res['success'] || empty($res['body']['data'])) return null;

        foreach ($res['body']['data'] as $p) {
            if (($p['codigo'] ?? '') === $sku) {
                // Se não é kit e não queremos kit, pula
                $f = strtoupper($p['formato'] ?? 'S');
                if (!$allowKit && ($f === 'E' || $f === 'C')) continue;

                $detalhe = $client->getProductById((int) $p['id']);
                return $detalhe ?? $p;
            }
        }
        return null;
    }

    /**
     * Realiza a atualização física do estoque (Balanço).
     */
    private function atualizarEstoque(int $produtoId, int $quantidade, ?int $depositoId = null): array
    {
        // Se ID do depósito não informado, tenta o padrão ou "Geral"
        if (!$depositoId) {
            $depositoId = $this->getDepositoIdPorNome($this->destino, 'Geral');
        }

        if (!$depositoId) {
            return ['success' => false, 'http_code' => 0, 'body' => ['error' => 'Depósito não identificado']];
        }

        $params = [
            'produto'     => ['id' => $produtoId],
            'deposito'    => ['id' => (int) $depositoId],
            'operacao'    => 'B',
            'preco'       => 0,
            'custo'       => 0,
            'quantidade'  => $quantidade,
            'observacoes' => 'Sync Automático: Deposit-to-Deposit Mirroring',
        ];

        $res = $this->destino->post('/estoques', [], $params);

        // Retry para rate limit (429) ou erro intermitente (5xx)
        if (!$res['success'] && in_array((int)($res['http_code'] ?? 0), [429, 500, 502, 503, 504])) {
            sleep(2);
            $res = $this->destino->post('/estoques', [], $params);
        }

        return $res;
    }
}
