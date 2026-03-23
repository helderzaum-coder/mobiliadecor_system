<?php

namespace App\Jobs;

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
        
        // Timeout mais longo para importações - 30 minutos
        $this->timeout = 1800;
    }

    public function handle(): void
    {
        Log::warning("=== INICIANDO IMPORTAÇÃO BLING ===", [
            'account' => $this->account,
            'data_inicio' => $this->dataInicio,
            'data_fim' => $this->dataFim,
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            $service = new BlingImportService($this->account);
            $inicio = microtime(true);
            
            $resultado = $service->importarParaStaging(
                $this->dataInicio,
                $this->dataFim
            );

            $duracao = round(microtime(true) - $inicio, 2);

            Log::warning("=== IMPORTAÇÃO BLING CONCLUÍDA ===", [
                'account' => $this->account,
                'importados' => $resultado['importados'],
                'ignorados' => $resultado['ignorados'],
                'erros' => $resultado['erros'],
                'duracao_segundos' => $duracao,
                'timestamp' => now()->toDateTimeString(),
            ]);

            if (!empty($resultado['mensagens'])) {
                Log::warning("Mensagens de importação:", $resultado['mensagens']);
            }
        } catch (\Exception $e) {
            Log::error("=== ERRO NA IMPORTAÇÃO BLING ===", [
                'error' => $e->getMessage(),
                'account' => $this->account,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->toDateTimeString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("=== JOB DE IMPORTAÇÃO BLING FALHOU ===", [
            'error' => $exception->getMessage(),
            'account' => $this->account,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
