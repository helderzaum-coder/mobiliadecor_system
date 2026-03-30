<?php

namespace App\Jobs;

use App\Services\Bling\BlingSyncEstoqueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job assíncrono para processar sincronização de estoque via webhook do Bling.
 *
 * O webhook retorna 200 imediatamente e o processamento pesado
 * (múltiplas chamadas de API ao Bling) ocorre em background,
 * evitando timeouts 504 nos workers do PHP-FPM.
 */
class BlingWebhookSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de tentativas em caso de falha.
     */
    public int $tries = 3;

    /**
     * Timeout máximo do job em segundos (90s é suficiente para chamadas à API do Bling).
     */
    public int $timeout = 90;

    /**
     * Tempo de espera entre tentativas (em segundos).
     */
    public int $backoff = 10;

    public function __construct(
        private readonly string $account,
        private readonly int    $produtoId,
        private readonly string $tipo // 'estoque' ou 'pedido'
    ) {}

    public function handle(): void
    {
        $service = new BlingSyncEstoqueService($this->account);

        if ($this->tipo === 'pedido') {
            $resultado = $service->processarPedido($this->produtoId);
        } else {
            $resultado = $service->espelharEstoque($this->produtoId, 0);
        }

        // Só loga se foi uma sincronização real (não loop, não ignorado)
        if (!($resultado['is_loop'] ?? false) && ($resultado['success'] ?? false)) {
            Log::info("BlingSync [{$this->account}]: {$this->tipo} #{$this->produtoId} processado.", [
                'log' => $resultado['log'] ?? [],
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("BlingWebhookSyncJob falhou", [
            'account'   => $this->account,
            'produtoId' => $this->produtoId,
            'tipo'      => $this->tipo,
            'error'     => $exception->getMessage(),
        ]);
    }
}
