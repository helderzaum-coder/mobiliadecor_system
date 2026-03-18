<?php

namespace App\Http\Controllers;

use App\Services\Bling\BlingSyncEstoqueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $evento  = $payload['event'] ?? null;
        $data    = $payload['data']  ?? [];

        Log::info("BlingWebhook [{$account}]: evento '{$evento}'", ['data' => $data]);

        try {
            // Pedido de venda criado ou atualizado
            if (in_array($evento, ['order.created', 'order.updated'])) {
                return $this->handlePedido($account, $data);
            }

            // Lançamento de estoque criado ou atualizado
            if (in_array($evento, ['stock.created', 'stock.updated'])) {
                return $this->handleEstoque($account, $data);
            }

            Log::info("BlingWebhook [{$account}]: evento '{$evento}' ignorado");
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

        $service   = new BlingSyncEstoqueService($account);
        $resultado = $service->processarPedido((int) $pedidoId);

        Log::info("BlingWebhook [{$account}]: pedido #{$pedidoId} processado", $resultado['log']);

        return response()->json(['status' => 'ok', 'pedido' => $pedidoId, 'log' => $resultado['log']]);
    }

    private function handleEstoque(string $account, array $data): \Illuminate\Http\JsonResponse
    {
        $produtoId = $data['produto']['id'] ?? null;
        $operacao  = $data['operacao']      ?? null;   // E=entrada, S=saída, B=balanço
        $saldo     = $data['saldoFisicoTotal'] ?? null;

        if (!$produtoId || $saldo === null) {
            return response()->json(['error' => 'Dados de estoque incompletos'], 422);
        }

        // Ignorar saídas — já tratadas pelo webhook de pedidos
        if ($operacao === 'S') {
            return response()->json(['status' => 'ignored', 'reason' => 'saida_tratada_por_pedido']);
        }

        // Anti-loop: verificar se foi o nosso sistema que gerou esta atualização
        $cacheKey = "bling_sync_loop_{$account}_{$produtoId}";
        if (Cache::has($cacheKey)) {
            Log::info("BlingWebhook [{$account}]: loop detectado para produto #{$produtoId} — ignorando");
            return response()->json(['status' => 'ignored', 'reason' => 'loop_prevention']);
        }

        $service   = new BlingSyncEstoqueService($account);
        $resultado = $service->espelharEstoque((int) $produtoId, (float) $saldo);

        Log::info("BlingWebhook [{$account}]: estoque produto #{$produtoId} espelhado", $resultado['log']);

        return response()->json(['status' => 'ok', 'produto_id' => $produtoId, 'log' => $resultado['log']]);
    }

    private function validarAssinatura(Request $request, string $account): bool
    {
        $signature = $request->header('X-Bling-Signature-256');

        // Se não veio assinatura, aceita (ambiente local / testes)
        if (!$signature) {
            return true;
        }

        $clientSecret = $account === 'primary'
            ? config('bling.primary.client_secret')
            : config('bling.secondary.client_secret');

        if (!$clientSecret) return true;

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $clientSecret);

        return hash_equals($expected, $signature);
    }
}
