<?php

namespace App\Jobs;

use App\Services\Bling\BlingImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessarWebhookPedidoBlingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private readonly string $account,
        private readonly int $pedidoId
    ) {}

    public function handle(): void
    {
        $service = new BlingImportService($this->account);
        $result = $service->importarPedidoPorId($this->pedidoId);
        Log::info("WebhookJob: pedido #{$this->pedidoId} ({$this->account}) -> " . ($result['status'] ?? 'ok'));
    }

    public function failed(\Throwable $e): void
    {
        Log::error("WebhookJob falhou: pedido #{$this->pedidoId}", ['error' => $e->getMessage()]);
    }
}
