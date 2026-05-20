<?php

namespace App\Jobs;

use App\Models\ProdutoEstoque;
use App\Models\TrocaTampoConfig;
use App\Services\EstoqueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AplicarLimiteTampoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function handle(): void
    {
        $configs = TrocaTampoConfig::whereNotNull('sku_tampo')
            ->where('sku_tampo', '!=', '')
            ->get();

        $atualizados = 0;
        $erros = 0;

        foreach ($configs as $config) {
            $produto = ProdutoEstoque::where('sku', $config->sku_produto)->where('ativo', true)->first();
            if (!$produto) continue;

            $tampo = ProdutoEstoque::where('sku', $config->sku_tampo)->where('ativo', true)->first();
            if (!$tampo) continue;

            if ($produto->saldo > $tampo->saldo) {
                $alvo = $tampo->saldo;

                // Primeiro zerar virtual se houver
                if ($produto->saldo_virtual > 0) {
                    EstoqueService::balanco($config->sku_produto, 0, 'limite_tampo', "Limitado por tampo {$tampo->sku}", null, false, 'virtual');
                }

                // Ajustar físico para o alvo
                $res = EstoqueService::balanco(
                    $config->sku_produto,
                    $alvo,
                    'limite_tampo',
                    "Limitado por tampo {$tampo->sku} (={$tampo->saldo})",
                    null,
                    true,
                    'fisico'
                );

                if ($res['success']) {
                    $atualizados++;
                    Log::info("AplicarLimiteTampo: {$config->sku_produto} limitado a {$alvo} por tampo {$tampo->sku}");
                } else {
                    $erros++;
                    Log::warning("AplicarLimiteTampo: erro {$config->sku_produto}: " . ($res['erro'] ?? '?'));
                }
            }
        }

        Log::info("AplicarLimiteTampo: concluído — atualizados={$atualizados}, erros={$erros}");

        $admins = \App\Models\User::role('admin')->get();
        foreach ($admins as $admin) {
            \Filament\Notifications\Notification::make()
                ->title("Limite de Tampos aplicado")
                ->body("Atualizados: {$atualizados} | Erros: {$erros}")
                ->icon($erros === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->iconColor($erros === 0 ? 'success' : 'warning')
                ->sendToDatabase($admin);
        }
    }
}
