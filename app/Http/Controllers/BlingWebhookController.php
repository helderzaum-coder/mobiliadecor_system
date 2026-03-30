<?php

namespace App\Http\Controllers;

use App\Services\Bling\BlingImportService;
use App\Services\Bling\BlingSyncEstoqueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  ATENÇÃO: CÓDIGO ESTÁVEL E FUNCIONAL — NÃO SOBRESCREVER           ║
 * ║                                                                    ║
 * ║  Webhook do Bling — recebe eventos em tempo real:                  ║
 * ║  - order.created/updated → sincroniza estoque + importa staging    ║
 * ║  - stock.created/updated → espelha saldo entre contas              ║
 * ║  - virtual_stock.updated → IGNORADO (só estoque físico)            ║
 * ║                                                                    ║
 * ║  Anti-loop via Cache (bling_sync_loop_*) e debounce (5s).          ║
 * ║  Validação HMAC opcional (X-Bling-Signature-256).                  ║
 * ║                                                                    ║
 * ║  Referência funcional: commit de 23/03/2026                        ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */
class BlingWebhookController extends Controller
{
    /**
     * Recebe webhook do Bling.
     * URL: POST /webhook/bling/{account}  ({account} = primary | secondary)
     *
     * Trata dois tipos de evento:
     *  - order.created / order.updated  → baixa estoque na conta oposta
     *  - stock.created / stock.updated  → espelha saldo na conta oposta (anti-loop via cache)
     */
    public function handle(Request $request, string $account): \Illuminate\Http\JsonResponse
    {
        if (!in_array($account, ['primary', 'secondary'])) {
            return response()->json(['error' => 'Conta inválida'], 400);
        }

        // Validar assinatura HMAC do Bling
        if (!$this->validarAssinatura($request, $account)) {
            Log::warning("BlingWebhook [{$account}]: assinatura inválida");
            return response()->json(['error' => 'Assinatura inválida'], 401);
        }

        $payload = $request->all();
        $evento  = strtolower((string) ($payload['event'] ?? $payload['tipo'] ?? $payload['type'] ?? ''));
        $data    = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        Log::info("BlingWebhook [{$account}]: evento '{$evento}'", ['data' => $data]);

        try {
            // Pedido de venda criado ou atualizado
            if ($this->isEventoPedido($evento, $data)) {
                return $this->handlePedido($account, $data);
            }

            // Lançamento de estoque criado ou atualizado
            if ($this->isEventoEstoque($evento, $data)) {
                return $this->handleEstoque($account, $data);
            }

      //      Log::info("BlingWebhook [{$account}]: evento '{$evento}' ignorado", [
                'payload_keys' => array_keys($payload),
                'data_keys' => is_array($data) ? array_keys($data) : [],
            ]);
            return response()->json(['status' => 'ignored', 'event' => $evento]);

        } catch (\Throwable $e) {
            Log::error("BlingWebhook [{$account}]: erro", [
                'event' => $evento,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------

    private function handlePedido(string $account, array $data): \Illuminate\Http\JsonResponse
    {
        $pedidoId = $data['id'] ?? null;

        if (!$pedidoId) {
            return response()->json(['error' => 'ID do pedido não encontrado'], 422);
        }

        // Verificar se o estoque deste pedido já foi sincronizado (persistente no banco)
        // Isso evita reprocessamento quando o Bling reenvia webhooks de pedidos antigos
        $estoqueJaSincronizado = \App\Models\PedidoBlingStaging::where('bling_id', $pedidoId)
            ->whereNotNull('bling_id')
            ->exists();

        // Debounce: não processar o mesmo pedido mais de uma vez a cada 5 minutos
        $debounceKey = "bling_pedido_debounce_{$account}_{$pedidoId}";
        if (Cache::has($debounceKey)) {
     //            Log::info("BlingWebhook [{$account}]: pedido #{$pedidoId} já processado recentemente — ignorando estoque");
     
            $logResult = ['estoque' => ['skipped' => 'debounce']];
            try {
                $importService = new BlingImportService($account);
                $importResult = $importService->importarPedidoPorId((int) $pedidoId);
                $logResult['staging'] = $importResult;
            } catch (\Throwable $e) {
                $logResult['staging'] = ['status' => 'erro', 'motivo' => $e->getMessage()];
            }
            return response()->json(['status' => 'ok', 'pedido' => $pedidoId, 'log' => $logResult]);
        }
        Cache::put($debounceKey, true, now()->addMinutes(5));

        $logResult = [];

        // 1. Sincronizar estoque na conta oposta — SOMENTE se o pedido ainda não existe no staging
        if ($estoqueJaSincronizado) {
          //  Log::info("BlingWebhook [{$account}]: pedido #{$pedidoId} já existe no staging — pulando sincronização de estoque");
            $logResult['estoque'] = ['skipped' => 'pedido_ja_existe_no_staging'];
        } else {
            $service   = new BlingSyncEstoqueService($account);
            $resultado = $service->processarPedido((int) $pedidoId);
            $logResult['estoque'] = $resultado['log'];
        }

        // 2. Importar pedido para o staging automaticamente (sempre tenta, pode ter dados novos)
        try {
            $importService = new BlingImportService($account);
            $importResult = $importService->importarPedidoPorId((int) $pedidoId);
            $logResult['staging'] = $importResult;
        //    Log::info("BlingWebhook [{$account}]: pedido #{$pedidoId} staging: {$importResult['status']}");
        } catch (\Throwable $e) {
            $logResult['staging'] = ['status' => 'erro', 'motivo' => $e->getMessage()];
            Log::warning("BlingWebhook [{$account}]: erro ao importar pedido #{$pedidoId} para staging", [
                'error' => $e->getMessage(),
            ]);
        }

     //   Log::info("BlingWebhook [{$account}]: pedido #{$pedidoId} processado", $logResult);

        return response()->json(['status' => 'ok', 'pedido' => $pedidoId, 'log' => $logResult]);
    }

    private function handleEstoque(string $account, array $data): \Illuminate\Http\JsonResponse
    {
        $produtoId = $data['produto']['id']
            ?? $data['produtoId']
            ?? $data['idProduto']
            ?? null;

        if (!$produtoId) {
            Log::warning("BlingWebhook [{$account}]: dados de estoque incompletos", ['data' => $data]);
            return response()->json(['error' => 'Dados de estoque incompletos'], 422);
        }

        // Anti-loop: verificar se foi o nosso sistema que gerou esta atualização
        $cacheKey = "bling_sync_loop_{$account}_{$produtoId}";
        if (Cache::has($cacheKey)) {
        //    Log::info("BlingWebhook [{$account}]: loop detectado para produto #{$produtoId} — ignorando");
            return response()->json(['status' => 'ignored', 'reason' => 'loop_prevention']);
        }

        // Debounce: evitar processar o mesmo produto várias vezes em sequência (30s)
        $debounceKey = "bling_webhook_debounce_{$account}_{$produtoId}";
        if (Cache::has($debounceKey)) {
            return response()->json(['status' => 'ignored', 'reason' => 'debounce']);
        }
        Cache::put($debounceKey, true, 30);

        $service   = new BlingSyncEstoqueService($account);
        $resultado = $service->espelharEstoque((int) $produtoId, 0); // saldo do webhook ignorado, busca real via API

        if (isset($resultado['is_loop']) && $resultado['is_loop']) {
            return response()->json(['status' => 'ignored loop', 'produto_id' => $produtoId], 200);
        }

     //   Log::info("BlingWebhook [{$account}]: estoque produto #{$produtoId} espelhado", $resultado['log']);

        return response()->json(['status' => 'ok', 'produto_id' => $produtoId, 'log' => $resultado['log']]);
    }

    private function validarAssinatura(Request $request, string $account): bool
    {
        $signature = $request->header('X-Bling-Signature-256')
            ?? $request->header('X-Bling-Signature')
            ?? $request->header('X-Hub-Signature-256');

        // Se não veio assinatura, aceita (ambiente local / testes)
        if (!$signature) {
            return true;
        }

        $clientSecret = config("bling.accounts.{$account}.client_secret");

        if (!$clientSecret) return true;

        $hash = hash_hmac('sha256', $request->getContent(), $clientSecret);
        $expected = 'sha256=' . $hash;

        return hash_equals($expected, $signature) || hash_equals($hash, $signature);
    }

    private function isEventoPedido(string $evento, array $data): bool
    {
        if (in_array($evento, ['order.created', 'order.updated', 'pedido.created', 'pedido.updated'])) {
            return true;
        }

        return isset($data['itens']) || isset($data['pedido']) || isset($data['numeroPedidoLoja']);
    }

    private function isEventoEstoque(string $evento, array $data): bool
    {
        if (in_array($evento, [
            'stock.created',
            'stock.updated',
            'estoque.created',
            'estoque.updated',
            'virtual_stock.updated',
            'virtual_stock.created',
        ])) {
            return true;
        }

        return isset($data['saldoFisicoTotal'])
            || isset($data['saldoFisico'])
            || isset($data['saldo'])
            || isset($data['produtoId'])
            || isset($data['idProduto'])
            || isset($data['produto']['id']);
    }
}
