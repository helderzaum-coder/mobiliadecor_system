<?php

namespace App\Http\Controllers;

use App\Services\Bling\BlingImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BlingWebhookController extends Controller
{
    public function handle(Request $request, string $account): \Illuminate\Http\JsonResponse
    {
        if (!in_array($account, ['primary', 'secondary'])) {
            return response()->json(['error' => 'Conta inválida'], 400);
        }

        if (!$this->validarAssinatura($request, $account)) {
            Log::warning("BlingWebhook [{$account}]: assinatura inválida");
            return response()->json(['error' => 'Assinatura inválida'], 401);
        }

        $payload = $request->all();
        $evento  = strtolower((string) ($payload['event'] ?? $payload['tipo'] ?? $payload['type'] ?? ''));
        $data    = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        try {
            if ($this->isEventoPedido($evento, $data)) {
                return $this->handlePedido($account, $data);
            }

            // Estoque desativado
            return response()->json(['status' => 'ignored', 'event' => $evento]);

        } catch (\Throwable $e) {
            Log::error("BlingWebhook [{$account}]: erro", [
                'event' => $evento,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function handlePedido(string $account, array $data): \Illuminate\Http\JsonResponse
    {
        $pedidoId = $data['id'] ?? null;

        if (!$pedidoId) {
            return response()->json(['error' => 'ID do pedido não encontrado'], 422);
        }

        $debounceKey = "bling_pedido_debounce_{$account}_{$pedidoId}";
        if (Cache::has($debounceKey)) {
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

        // Importar pedido para o staging
        try {
            $importService = new BlingImportService($account);
            $importResult = $importService->importarPedidoPorId((int) $pedidoId);
            $logResult['staging'] = $importResult;
        } catch (\Throwable $e) {
            $logResult['staging'] = ['status' => 'erro', 'motivo' => $e->getMessage()];
            Log::warning("BlingWebhook [{$account}]: erro ao importar pedido #{$pedidoId} para staging", [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'ok', 'pedido' => $pedidoId, 'log' => $logResult]);
    }

    private function validarAssinatura(Request $request, string $account): bool
    {
        $signature = $request->header('X-Bling-Signature-256')
            ?? $request->header('X-Bling-Signature')
            ?? $request->header('X-Hub-Signature-256');

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
}
