<?php

namespace App\Jobs;

use App\Models\PedidoBlingStaging;
use App\Models\User;
use App\Models\Venda;
use App\Services\Bling\BlingImportService;
use App\Services\VendaRecalculoService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuscarDadosVendaLoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(
        private readonly string $tipo,
        private readonly array $vendaIds,
        private readonly int $userId,
    ) {}

    public function handle(): void
    {
        $ok = 0;
        $falha = 0;

        foreach ($this->vendaIds as $id) {
            $venda = Venda::find($id);
            if (!$venda) { $falha++; continue; }

            $result = match ($this->tipo) {
                'nfe' => $this->processarNfe($venda),
                'cte' => $this->processarCte($venda),
                'custos' => $this->processarCustos($venda),
                default => false,
            };

            $result ? $ok++ : $falha++;
        }

        $label = match ($this->tipo) {
            'nfe' => 'NF-e',
            'cte' => 'CT-e',
            'custos' => 'Custos',
            default => $this->tipo,
        };

        $user = User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title("{$label} em lote concluído")
                ->body("Processados: {$ok} | Não encontrados: {$falha}")
                ->icon($ok > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->iconColor($ok > 0 ? 'success' : 'warning')
                ->sendToDatabase($user);
        }

        Log::info("BuscarDadosVendaLote [{$this->tipo}]: ok={$ok} falha={$falha}");
    }

    private function processarNfe(Venda $venda): bool
    {
        return VendaRecalculoService::buscarNfe($venda)['success'];
    }

    private function processarCte(Venda $venda): bool
    {
        return VendaRecalculoService::buscarCte($venda)['success'];
    }

    private function processarCustos(Venda $venda): bool
    {
        $staging = PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
        if (!$staging) return false;

        $atualizados = BlingImportService::buscarCustosProdutos($staging);
        if ($atualizados <= 0) return false;

        $custoProdutos = 0;
        foreach ($staging->fresh()->itens ?? [] as $item) {
            $custoProdutos += ((float) ($item['custo'] ?? 0)) * ((int) ($item['quantidade'] ?? 1));
        }
        $venda->update(['custo_produtos' => round($custoProdutos, 2)]);
        VendaRecalculoService::recalcularMargens($venda);
        return true;
    }
}
