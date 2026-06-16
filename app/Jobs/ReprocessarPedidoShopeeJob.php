<?php

namespace App\Jobs;

use App\Models\PedidoBlingStaging;
use App\Services\Shopee\ShopeeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReprocessarPedidoShopeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * O número de vezes que o job pode ser tentado.
     */
    public $tries = 3;

    /**
     * O número de segundos a aguardar antes de tentar novamente o job.
     */
    public $backoff = 60;

    protected $staging;

    /**
     * Cria uma nova instância de Job.
     */
    public function __construct(PedidoBlingStaging $staging)
    {
        // O SerializesModels garante que apenas o ID do model seja guardado na fila
        $this->staging = $staging;
    }

    /**
     * Executa o Job.
     */
    public function handle(): void
    {
        // Valida se o registro ainda existe ou se foi apagado antes do processamento
        if (!$this->staging || !$this->staging->exists) {
            return;
        }

        Log::info('Job Shopee: Iniciando reprocessamento', ['id' => $this->staging->id, 'numero_loja' => $this->staging->numero_loja]);

        // Chama o método estruturado no seu ShopeeService
        ShopeeService::reprocessarPedido($this->staging);
    }

    /**
     * Trata uma falha no Job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job Shopee: Falha definitiva ao reprocessar pedido', [
            'id' => $this->staging->id,
            'numero_loja' => $this->staging->numero_loja,
            'erro' => $exception->getMessage()
        ]);
    }
}
