<?php

namespace App\Services\Bling;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza estoque entre as duas contas Bling via webhook.
 * Quando um pedido é criado/atualizado em uma conta, baixa o estoque
 * dos produtos vendidos na outra conta (e espelha de volta).
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
     * Espelha o saldo de estoque de um produto da conta origem para a destino.
     * Busca saldo REAL via /estoques/saldos (por depósito) e soma apenas
     * Geral + Estoque Virtual (ignora Aguardando Retorno e Reserva Garantia).
     */
    public function espelharEstoque(int $produtoIdOrigem, float $saldoWebhook): array
    {
        $log = [];

        // Buscar detalhes do produto na origem
        $prodOrigem = $this->origem->getProductById($produtoIdOrigem);

        if (!$prodOrigem) {
            $msg = "Produto ID {$produtoIdOrigem} não encontrado na origem ({$this->origemKey})";
            Log::warning("BlingSyncEstoque: {$msg}");
            return ['success' => false, 'log' => [$msg]];
        }

        $sku     = $prodOrigem['codigo'] ?? null;
        $formato = strtoupper($prodOrigem['formato'] ?? 'S');

        if (!$sku) {
            $msg = "Produto ID {$produtoIdOrigem} sem SKU — pulando";
            Log::warning("BlingSyncEstoque: {$msg}");
            return ['success' => false, 'log' => [$msg]];
        }

        // Buscar produto na conta destino
        $prodDestino = $this->buscarProdutoCompleto($this->destino, $sku, true);

        if (!$prodDestino) {
            $log[] = "SKU {$sku}: não encontrado no destino ({$this->destinoKey}) — pulando";
            return ['success' => true, 'log' => $log];
        }

        $prodDestinoId = (int) ($prodDestino['id'] ?? 0);

        // Anti-loop (TTL 10 minutos para maior segurança)
        $cacheKey = "bling_sync_loop_{$this->destinoKey}_{$prodDestinoId}";
        Cache::put($cacheKey, true, now()->addMinutes(10));

        // CASO 1: Kit (Formato E ou C) — Não tem depósitos físicos, usa saldo consolidado
        if ($formato === 'E' || $formato === 'C') {
            $saldoTotal = (int) ($prodOrigem['estoque']['saldoVirtualTotal'] ?? 0);
            $log[] = "SKU {$sku} (Kit): espelhando saldo total {$saldoTotal}";
            
            $res = $this->atualizarEstoque($prodDestinoId, $saldoTotal, null);
            if ($res['success']) {
                $log[] = "✓ saldo total do Kit espelhado em {$this->destinoKey}";
            }
            return ['success' => $res['success'], 'log' => $log];
        }

        // CASO 2: Produto Simples ou Variação — Sincronização Depósito a Depósito
        $saldosOrigem = $this->buscarSaldosPorDeposito($this->origem, $produtoIdOrigem);

        if (!$saldosOrigem) {
            $log[] = "SKU {$sku}: não foi possível segmentar saldos por depósito — usando saldo consolidado";
            $saldoTotal = (int) ($prodOrigem['estoque']['saldoVirtualTotal'] ?? 0);
            $res = $this->atualizarEstoque($prodDestinoId, $saldoTotal, null);
            return ['success' => $res['success'], 'log' => $log];
        }

        $success = true;
        foreach ($saldosOrigem as $nomeDeposito => $quantidade) {
            $log[] = "Deposit '{$nomeDeposito}': saldo {$quantidade}";
            
            // Buscar ID do depósito correspondente no destino pelo nome
            $depDestinoId = $this->getDepositoIdPorNome($this->destino, $nomeDeposito);
            
            if ($depDestinoId) {
                $res = $this->atualizarEstoque($prodDestinoId, $quantidade, $depDestinoId);
                if ($res['success']) {
                    $log[] = "  ✓ atualizado no destino (depósito ID {$depDestinoId})";
                } else {
                    $log[] = "  ✗ falha ao atualizar depósito '{$nomeDeposito}'";
                    $success = false;
                }
            } else {
                $log[] = "  ! depósito '{$nomeDeposito}' não existe no destino — pulando";
            }
        }

        return ['success' => $success, 'log' => $log];
    }

    /**
     * Busca saldos segmentados por depósito.
     * Retorna array: ['Nome do Depósito' => saldo, ...]
     */
    private function buscarSaldosPorDeposito(BlingClient $client, int $produtoId): ?array
    {
        $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $produtoId]);

        if (!$res['success'] || empty($res['body']['data'])) {
            return null;
        }

        $dados = $res['body']['data'][0] ?? null;
        if (!$dados) return null;

        $depositosRes = $client->get('/depositos', ['limite' => 100]);
        if (!$depositosRes['success']) return null;

        $depositoNomes = [];
        foreach ($depositosRes['body']['data'] ?? [] as $dep) {
            $depositoNomes[$dep['id']] = $dep['descricao'] ?? '';
        }

        $saldos = [];
        foreach ($dados['depositos'] ?? [] as $dep) {
            $nome = $depositoNomes[$dep['id']] ?? null;
            if ($nome) {
                // Filtrar apenas depósitos que o usuário usa para venda (Geral ou Virtual)
                $nomeLower = strtolower($nome);
                if (str_contains($nomeLower, 'geral') || str_contains($nomeLower, 'virtual') || str_contains($nomeLower, 'virtal')) {
                    $saldos[$nome] = (int) ($dep['saldoFisico'] ?? 0);
                }
            }
        }

        return !empty($saldos) ? $saldos : null;
    }

    /**
     * Helper para encontrar um depósito pelo nome (case insensitive) em uma conta.
     */
    private function getDepositoIdPorNome(BlingClient $client, string $nome): ?int
    {
        $cacheKey = "bling_deposito_id_{$nome}_{$client->getHost()}";
        $id = Cache::get($cacheKey);
        if ($id) return (int) $id;

        $res = $client->get('/depositos', ['limite' => 100]);
        if (!$res['success']) return null;

        $nomeTarget = strtolower(trim($nome));

        foreach ($res['body']['data'] ?? [] as $dep) {
            $nomeAtual = strtolower(trim($dep['descricao'] ?? ''));
            if ($nomeAtual === $nomeTarget) {
                Cache::put($cacheKey, $dep['id'], now()->addDays(1));
                return (int) $dep['id'];
            }
        }

        return null;
    }

    /**
     * Processa um pedido recebido via webhook.
     * Busca os itens do pedido e sincroniza o estoque na conta destino.
     */
    public function processarPedido(int $pedidoId): array
    {
        $log = [];

        // Buscar detalhes completos do pedido na conta de origem
        $res = $this->origem->getPedido($pedidoId);

        if (!$res['success']) {
            $msg = "Erro ao buscar pedido #{$pedidoId} na conta {$this->origemKey}: HTTP {$res['http_code']}";
            Log::error("BlingSyncEstoque: {$msg}");
            return ['success' => false, 'log' => [$msg]];
        }

        $pedido = $res['body']['data'] ?? null;

        if (!$pedido) {
            $msg = "Pedido #{$pedidoId} não encontrado na conta {$this->origemKey}";
            Log::warning("BlingSyncEstoque: {$msg}");
            return ['success' => false, 'log' => [$msg]];
        }

        $itens = $pedido['itens'] ?? [];

        if (empty($itens)) {
            $msg = "Pedido #{$pedidoId} sem itens — aguardando faturamento";
            Log::info("BlingSyncEstoque: {$msg}");
            return ['success' => true, 'log' => [$msg]];
        }

        $log[] = "Pedido #{$pedidoId} ({$this->origemKey}): " . count($itens) . " item(ns)";

        // Agrupa SKUs e quantidades
        $skuQtd = [];
        foreach ($itens as $item) {
            $sku = $item['codigo'] ?? null;
            $qtd = (int) ($item['quantidade'] ?? 1);
            if ($sku) {
                $skuQtd[$sku] = ($skuQtd[$sku] ?? 0) + $qtd;
            }
        }

        foreach ($skuQtd as $sku => $qtdVendida) {
            $resultado = $this->sincronizarSku($sku, $qtdVendida);
            $log = array_merge($log, $resultado['log']);
        }

        return ['success' => true, 'log' => $log];
    }

    /**
     * Sincroniza um SKU: detecta se é simples, variação ou kit,
     * e atualiza o estoque na conta destino.
     */
    private function sincronizarSku(string $sku, int $qtdVendida): array
    {
        $log = [];

        // Buscar produto na conta de ORIGEM para detectar o tipo
        $prodOrigem = $this->buscarProdutoCompleto($this->origem, $sku, true);

        if (!$prodOrigem) {
            $log[] = "  SKU {$sku}: não encontrado na origem ({$this->origemKey}) — pulando";
            return ['log' => $log];
        }

        $formato = strtoupper($prodOrigem['formato'] ?? 'S');
        $log[] = "  SKU {$sku} x{$qtdVendida} — formato: {$formato}";

        if ($formato === 'E' || $formato === 'C') {
            // Kit: sincroniza cada componente
            $componentes = $prodOrigem['estrutura']['componentes'] ?? [];

            if (empty($componentes)) {
                $log[] = "    Kit sem componentes definidos — pulando";
                return ['log' => $log];
            }

            $log[] = "    Kit com " . count($componentes) . " componente(s)";

            foreach ($componentes as $comp) {
                $compId  = $comp['produto']['id'] ?? null;
                $compQtd = (float) ($comp['quantidade'] ?? 1);

                if (!$compId) continue;

                // Buscar SKU do componente na origem
                $compOrigem = $this->origem->getProductById((int) $compId);
                if (!$compOrigem) {
                    $log[] = "    Componente ID {$compId}: não encontrado — pulando";
                    continue;
                }

                $compSku   = $compOrigem['codigo'] ?? null;
                $totalQtd  = (int) ceil($qtdVendida * $compQtd);

                if (!$compSku) continue;

                $resultado = $this->atualizarEstoqueDestino($compSku, $totalQtd);
                $log = array_merge($log, array_map(fn($l) => "    {$l}", $resultado['log']));
            }
        } else {
            // Produto simples ou variação
            $resultado = $this->atualizarEstoqueDestino($sku, $qtdVendida);
            $log = array_merge($log, array_map(fn($l) => "  {$l}", $resultado['log']));
        }

        return ['log' => $log];
    }

    /**
     * Busca o produto na conta destino, calcula novo estoque e atualiza.
     */
    private function atualizarEstoqueDestino(string $sku, int $qtdBaixar): array
    {
        $log = [];

        $prodDestino = $this->buscarProdutoCompleto($this->destino, $sku);

        if (!$prodDestino) {
            $log[] = "SKU {$sku}: não encontrado no destino ({$this->destinoKey}) — pulando";
            return ['log' => $log];
        }

        $prodId       = $prodDestino['id'] ?? null;
        $estoqueAtual = (int) ($prodDestino['estoque']['saldoVirtualTotal'] ?? 0);
        $novoEstoque  = max(0, $estoqueAtual - $qtdBaixar);

        $log[] = "SKU {$sku}: estoque {$estoqueAtual} → {$novoEstoque} (-{$qtdBaixar})";

        // Marcar cache anti-loop (10 minutos)
        $cacheKey = "bling_sync_loop_{$this->destinoKey}_{$prodId}";
        Cache::put($cacheKey, true, now()->addMinutes(10));

        $res = $this->atualizarEstoque((int) $prodId, $novoEstoque, null);

        if ($res['success']) {
            $log[] = "SKU {$sku}: ✓ atualizado no destino ({$this->destinoKey})";
            Log::info("BlingSyncEstoque: SKU {$sku} atualizado {$estoqueAtual}→{$novoEstoque} em {$this->destinoKey}");
        } else {
            $log[] = "SKU {$sku}: ✗ erro HTTP {$res['http_code']} ao atualizar no destino";
            Log::error("BlingSyncEstoque: Erro ao atualizar SKU {$sku} em {$this->destinoKey}", $res);
        }

        return ['log' => $log];
    }

    /**
     * Busca produto pelo SKU com detalhes completos (estrutura, estoque).
     */
    private function buscarProdutoCompleto(BlingClient $client, string $sku, bool $allowKit = false): ?array
    {
        // Busca resumida pelo SKU
        $res = $client->get('/produtos', ['codigo' => $sku, 'limite' => 100]);

        if (!$res['success'] || empty($res['body']['data'])) {
            return null;
        }

        foreach ($res['body']['data'] as $produto) {
            if (($produto['codigo'] ?? '') !== $sku) continue;

            $formato = strtoupper($produto['formato'] ?? 'S');

            // Se não é kit e não queremos kit, aceita direto
            if (!$allowKit && ($formato === 'E' || $formato === 'C')) continue;

            // Buscar detalhes completos pelo ID para ter estrutura e estoque
            $detalhe = $client->getProductById((int) $produto['id']);
            return $detalhe ?? $produto;
        }

        return null;
    }

    /**
     * Atualiza estoque via endpoint /estoques (balanço).
     * Fallback: PUT no produto.
     */
    /**
     * Atualiza estoque via endpoint /estoques (balanço).
     */
    private function atualizarEstoque(int $produtoId, int $quantidade, ?int $depositoId = null): array
    {
        // Se depósito não informado, tenta encontrar o PADRÃO ou "Geral"
        if (!$depositoId) {
            $depositos = $this->destino->get('/depositos', ['limite' => 100]);
            foreach ($depositos['body']['data'] ?? [] as $dep) {
                if (!empty($dep['padrao']) || str_contains(strtolower($dep['descricao'] ?? ''), 'geral')) {
                    $depositoId = $dep['id'];
                    break;
                }
            }
        }

        if ($depositoId) {
            $res = $this->destino->post('/estoques', [], [
                'produto'    => ['id' => $produtoId],
                'deposito'   => ['id' => (int) $depositoId],
                'operacao'   => 'B',
                'preco'      => 0,
                'custo'      => 0,
                'quantidade' => $quantidade,
                'observacoes'=> 'Sincronização automática via webhook',
            ]);

            if ($res['success']) return $res;

            if ($res['http_code'] === 429) {
                sleep(2);
                $res = $this->destino->post('/estoques', [], [
                    'produto'    => ['id' => $produtoId],
                    'deposito'   => ['id' => (int) $depositoId],
                    'operacao'   => 'B',
                    'preco'      => 0,
                    'custo'      => 0,
                    'quantidade' => $quantidade,
                    'observacoes'=> 'Sincronização automática via webhook',
                ]);
                if ($res['success']) return $res;
            }
        }

        return ['success' => false, 'http_code' => 0, 'body' => ['error' => 'Depósito não encontrado']];
    }
}
