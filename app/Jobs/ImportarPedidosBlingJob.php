<?php

namespace App\Jobs;

use App\Models\PedidoBlingStaging;
use App\Services\Bling\BlingImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportarPedidosBlingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $account;
    private string $dataInicio;
    private string $dataFim;

    public function __construct(string $account, string $dataInicio, string $dataFim)
    {
        $this->account = $account;
        $this->dataInicio = $dataInicio;
        $this->dataFim = $dataFim;
        
        // Aumentar timeout para importações longas
        $this->timeout = 600; // 10 minutos
    }

    public function handle(): void
    {
        Log::info("Iniciando importação de pedidos Bling", [
            'account' => $this->account,
            'data_inicio' => $this->dataInicio,
            'data_fim' => $this->dataFim,
        ]);

        try {
            $service = new BlingImportService($this->account);
            $resultado = $service->importarParaStaging(
                $this->dataInicio,
                $this->dataFim
            );

            Log::info("Importação Bling concluída com sucesso", [
                'resultado' => $resultado,
            ]);
        } catch (\Exception $e) {
            Log::error("Erro na importação de pedidos Bling", [
                'error' => $e->getMessage(),
                'account' => $this->account,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job de importação Bling falhou", [
            'error' => $exception->getMessage(),
            'account' => $this->account,
        ]);
    }
}
