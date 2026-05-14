<?php

namespace App\Jobs;

use App\Models\ProdutoEstoque;
use App\Services\Bling\BlingClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSaldoSecondaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function handle(): void
    {
        $client = new BlingClient('secondary');
        $produtos = ProdutoEstoque::where('ativo', true)->get();
        $atualizados = 0;

        foreach ($produtos as $produto) {
            $blingProd = $client->getProductBySku($produto->sku);
            if (!$blingProd) continue;

            $prodId = (int) $blingProd['id'];
            $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $prodId]);

            if (!$res['success'] || empty($res['body']['data'])) continue;

            $total = 0;
            foreach ($res['body']['data'][0]['depositos'] ?? [] as $dep) {
                $total += (int) ($dep['saldoFisico'] ?? 0);
            }

            $produto->update(['saldo_secondary' => $total]);
            $atualizados++;
        }

        Log::info("SyncSaldoSecondary: {$atualizados} produtos atualizados");

        $admins = \App\Models\User::role('admin')->get();
        foreach ($admins as $admin) {
            \Filament\Notifications\Notification::make()
                ->title("Saldos Secondary sincronizados")
                ->body("{$atualizados} produto(s) atualizado(s).")
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->sendToDatabase($admin);
        }
    }
}
