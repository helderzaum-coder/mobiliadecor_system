<?php

namespace App\Services\Bling;

class BlingSyncEstoqueService
{
    public function __construct(string $origemKey) {}

    public function espelharEstoque(int $produtoIdOrigem, float $saldoWebhook): array
    {
        return ['success' => false, 'log' => ['Sincronização desativada']];
    }

    public function processarPedido(int $pedidoId): array
    {
        return ['success' => false, 'log' => ['Sincronização desativada']];
    }
}
