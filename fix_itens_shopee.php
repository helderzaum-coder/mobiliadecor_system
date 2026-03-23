<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PedidoBlingStaging;

// Corrigir pedidos Shopee que perderam SKU/descrição dos itens
$pedidos = PedidoBlingStaging::where('canal', 'like', '%hopee%')
    ->where('status', 'pendente')
    ->get();

$corrigidos = 0;
foreach ($pedidos as $staging) {
    $itens = $staging->itens ?? [];
    $itensOriginais = $staging->dados_originais['itens'] ?? [];
    $precisaCorrigir = false;

    foreach ($itens as $i => $item) {
        if (empty($item['codigo']) && !empty($itensOriginais[$i]['codigo'])) {
            $precisaCorrigir = true;
            break;
        }
    }

    if (!$precisaCorrigir) continue;

    $itensCorrigidos = [];
    foreach ($itens as $i => $item) {
        $orig = $itensOriginais[$i] ?? [];
        $itensCorrigidos[] = [
            'codigo' => !empty($item['codigo']) ? $item['codigo'] : ($orig['codigo'] ?? ''),
            'descricao' => !empty($item['descricao']) ? $item['descricao'] : ($orig['descricao'] ?? ''),
            'quantidade' => $item['quantidade'] ?? ($orig['quantidade'] ?? 1),
            'valor' => $item['valor'] ?? ($orig['valor'] ?? 0),
            'custo' => $item['custo'] ?? null,
        ];
    }

    $staging->update(['itens' => $itensCorrigidos]);
    $corrigidos++;
    echo "Corrigido: {$staging->numero_loja} | SKU: {$itensCorrigidos[0]['codigo']} | {$itensCorrigidos[0]['descricao']}\n";
}

echo "\n{$corrigidos} pedidos corrigidos\n";
