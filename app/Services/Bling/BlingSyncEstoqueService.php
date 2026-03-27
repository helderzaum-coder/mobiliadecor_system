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

        // Buscar SKU do produto na origem pelo ID
        $prodOrigem = $this->origem->getProductById($produtoIdOrigem);

        if (!$prodOrigem) {
            $msg = "Produto ID {$produtoIdOrigem} não encontrado na origem ({$this->origemKey})";
            Log::warning("BlingSyncEstoque: {$msg}");
            return ['success' => false, 'log' => [$msg]];
        }

        $sku = $prodOrigem['codigo'] ?? null;

        if (!$sku) {
            $msg = "Produto ID {$produtoIdOrigem} sem SKU — pulando";
            Log::warning("BlingSyncEstoque: {$msg}");
            return ['success' => false, 'log' => [$msg]];
        }

        // Buscar saldo REAL por depósito via API (não confiar no webhook)
        $saldoReal = $this->buscarSaldoDisponivel($this->origem, $produtoIdOrigem);
        if ($saldoReal === null) {
            $log[] = "SKU {$sku}: não foi possível buscar saldo por depósito — usando saldoVirtualTotal do produto";
            $saldoReal = (int) ($prodOrigem['estoque']['saldoVirtualTotal'] ?? 0);
        }

        $log[] = "Espelhando estoque SKU {$sku}: saldo disponível {$saldoReal} ({$this->origemKey} → {$this->destinoKey})";

        // Buscar produto na conta destino
        $prodDestino = $this->buscarProdutoCompleto($this->destino, $sku);

        if (!$prodDestino) {
            $log[] = "SKU {$sku}: não encontrado no destino ({$this->destinoKey}) — pulando";
            return ['success' => true, 'log' => $log];
        }

        $prodDestinoId = (int) ($prodDestino['id'] ?? 0);

        // Marcar cache anti-loop ANTES de atualizar (TTL 10 minutos)
        $cacheKey = "bling_sync_loop_{$this->destinoKey}_{$prodDestinoId}";
        Cache::put($cacheKey, true, now()->addMinutes(10));

        // Salvar saldo sincronizado no cache (TTL 10 minutos)
        $saldoCacheKey = "bling_last_synced_saldo_{$this->destinoKey}_{$prodDestinoId}";
        $lastSyncedSaldo = Cache::get($saldoCacheKey);
        if ($lastSyncedSaldo !== null && (int)$lastSyncedSaldo === (int)$saldoReal) {
            $log[] = "SKU {$sku}: saldo já sincronizado = {$saldoReal} em {$this->destinoKey} — pulando";
            return ['success' => true, 'log' => $log];
        }

        $res = $this->atualizarEstoque($prodDestinoId, $saldoReal, $prodDestino);

        if ($res['success']) {
            $log[] = "SKU {$sku}: ✓ estoque espelhado = {$saldoReal} em {$this->destinoKey}";
            Log::info("BlingSyncEstoque: SKU {$sku} espelhado saldo={$saldoReal} em {$this->destinoKey}");
            Cache::put($saldoCacheKey, $saldoReal, now()->addMinutes(10));
        } else {
            $log[] = "SKU {$sku}: ✗ erro HTTP {$res['http_code']} ao espelhar em {$this->destinoKey}";
            Log::error("BlingSyncEstoque: Erro ao espelhar SKU {$sku} em {$this->destinoKey}", $res);
        }

        return ['success' => $res['success'], 'log' => $log];
    }

    /**
     * Busca saldo disponível para venda de um produto (Geral + Estoque Virtual).
     * Ignora depósitos Aguardando Retorno e Reserva Garantia.
     */
    private function buscarSaldoDisponivel(BlingClient $client, int $produtoId): ?int
    {
        $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $produtoId]);

        if (!$res['success'] || empty($res['body']['data'])) {
            return null;
        }

        $dados = $res['body']['data'][0] ?? null;
        if (!$dados) return null;

        // Buscar nomes dos depósitos para filtrar
        $depositosRes = $client->get('/depositos', ['limite' => 100]);
        $depositoNomes = [];
        foreach ($depositosRes['body']['data'] ?? [] as $dep) {
            $depositoNomes[$dep['id']] = strtolower($dep['descricao'] ?? '');
        }

        $saldo = 0;
        foreach ($dados['depositos'] ?? [] as $dep) {
            $nome = $depositoNomes[$dep['id']] ?? '';
            // Incluir apenas Geral e Estoque Virtual
            if (str_contains($nome, 'geral') || str_contains($nome, 'virtual')) {
                $saldo += (int) ($dep['saldoFisico'] ?? 0);
            }
            // Ignorar: Aguardando Retorno, Reserva Garantia, etc.
        }

        return $saldo;
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

        // Marcar cache anti-loop ANTES de atualizar (TTL 5 minutos)
        $cacheKey = "bling_sync_loop_{$this->destinoKey}_{$prodId}";
        Cache::put($cacheKey, true, now()->addMinutes(5));

        $res = $this->atualizarEstoque((int) $prodId, $novoEstoque, $prodDestino);

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
    private function atualizarEstoque(int $produtoId, int $quantidade, array $produto): array
    {
        // Buscar o depósito PADRÃO (Geral) — nunca usar o primeiro da lista
        $depositos = $this->destino->get('/depositos', ['limite' => 100]);
        $depositoId = null;
        foreach ($depositos['body']['data'] ?? [] as $dep) {
            if (!empty($dep['padrao'])) {
                $depositoId = $dep['id'];
                break;
            }
        }
        // Fallback: se nenhum marcado como padrão, buscar pelo nome "Geral"
        if (!$depositoId) {
            foreach ($depositos['body']['data'] ?? [] as $dep) {
                if (str_contains(strtolower($dep['descricao'] ?? ''), 'geral')) {
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

        // Fallback: logar erro — NÃO usar PUT /produtos para evitar alterar medidas/dados do produto
        Log::error("BlingSyncEstoque: Não foi possível atualizar estoque do produto {$produtoId} via POST /estoques e depósito não encontrado", [
            'produto_id' => $produtoId,
            'quantidade'  => $quantidade,
        ]);

        return ['success' => false, 'http_code' => 0, 'body' => ['error' => 'Depósito padrão não encontrado']];
    }
}
