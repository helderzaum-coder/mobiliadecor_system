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
    private ?string $canalFiltro;

    public function __construct(string $account, string $dataInicio, string $dataFim, ?string $canalFiltro = null)
    {
        $this->account = $account;
        $this->dataInicio = $dataInicio;
        $this->dataFim = $dataFim;
        $this->canalFiltro = $canalFiltro;
        $this->timeout = 1800;
    }

    public function handle(): void
    {
        Log::warning("=== INICIANDO IMPORTAÇÃO BLING ===", [
            'account' => $this->account,
            'data_inicio' => $this->dataInicio,
            'data_fim' => $this->dataFim,
            'canal_filtro' => $this->canalFiltro,
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            $service = new BlingImportService($this->account);
            $inicio = microtime(true);

            $resultado = $service->importarParaStaging(
                $this->dataInicio,
                $this->dataFim,
                $this->canalFiltro
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

            $conta = $this->account === 'primary' ? 'Mobilia' : 'HES';
            $admins = \App\Models\User::role('admin')->get();
            foreach ($admins as $admin) {
                \Filament\Notifications\Notification::make()
                    ->title("Importação Bling concluída ({$conta})")
                    ->body("{$resultado['importados']} importados, {$resultado['ignorados']} ignorados, {$resultado['erros']} erros — {$duracao}s")
                    ->icon($resultado['erros'] > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->iconColor($resultado['erros'] > 0 ? 'warning' : 'success')
                    ->sendToDatabase($admin);
            }
        } catch (\Exception $e) {
            Log::error("=== ERRO NA IMPORTAÇÃO BLING ===", [
                'error' => $e->getMessage(),
                'account' => $this->account,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->toDateTimeString(),
            ]);

            $conta = $this->account === 'primary' ? 'Mobilia' : 'HES';
            $admins = \App\Models\User::role('admin')->get();
            foreach ($admins as $admin) {
                \Filament\Notifications\Notification::make()
                    ->title("Erro na importação Bling ({$conta})")
                    ->body($e->getMessage())
                    ->icon('heroicon-o-x-circle')
                    ->iconColor('danger')
                    ->sendToDatabase($admin);
            }

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
