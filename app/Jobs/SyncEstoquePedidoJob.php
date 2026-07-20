<?php

namespace App\Jobs;

use App\Models\PedidoBlingStaging;
use App\Services\Bling\BlingEstoquePedidoService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEstoquePedidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;
    public int $backoff = 10;

    public function __construct(
        private readonly int $pedidoId
    ) {}

    public function handle(): void
    {
        $pedido = PedidoBlingStaging::find($this->pedidoId);
        if (!$pedido || $pedido->estoque_sincronizado) return;

        $resultado = BlingEstoquePedidoService::sincronizar($pedido);

        // Notificar admins
        $conta = $pedido->bling_account === 'primary' ? 'Mobilia' : 'HES';
        $admins = \App\Models\User::role('admin')->get();

        foreach ($admins as $admin) {
            \Filament\Notifications\Notification::make()
                ->title("Estoque sincronizado — Pedido #{$pedido->numero_pedido} ({$conta})")
                ->body($resultado['success']
                    ? implode(' | ', $resultado['log'])
                    : "Com {$resultado['erros']} erro(s): " . implode(' | ', $resultado['log']))
                ->icon($resultado['success'] ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->iconColor($resultado['success'] ? 'success' : 'warning')
                ->sendToDatabase($admin);
        }

        // Notificação Telegram com saldo atualizado
        if (!empty($resultado['log'])) {
            $icone = $resultado['success'] ? "\xF0\x9F\x93\xA6" : "\xE2\x9A\xA0\xEF\xB8\x8F";
            $msg = "{$icone} <b>Estoque — Pedido #{$pedido->numero_pedido}</b>\n";
            foreach ($resultado['log'] as $linha) {
                $skuLog = explode(':', $linha)[0] ?? '';
                $prod = \App\Models\ProdutoEstoque::where('sku', trim($skuLog))->first();
                $nomeProd = $prod ? ($prod->observacoes ?? $prod->nome) : '';
                $msg .= "• {$linha}";
                if ($nomeProd) {
                    $msg .= " — {$nomeProd}";
                }
                $msg .= "\n";
            }
            TelegramService::enviar($msg);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncEstoquePedidoJob falhou", [
            'pedido_id' => $this->pedidoId,
            'error' => $exception->getMessage(),
        ]);
    }
}
