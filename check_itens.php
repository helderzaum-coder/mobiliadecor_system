<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PedidoBlingStaging;

$staging = PedidoBlingStaging::where('numero_loja', '2603023DFJ1VMU')->first();
if (!$staging) {
    echo "Nao encontrado por numero_loja, buscando por numero_pedido...\n";
    $staging = PedidoBlingStaging::where('numero_pedido', 'like', '%2603023DFJ1VMU%')->first();
}
if (!$staging) {
    echo "Nao encontrado\n";
    exit;
}

echo "=== STAGING ===\n";
echo "id: {$staging->id}\n";
echo "bling_id: {$staging->bling_id}\n";
echo "numero_loja: {$staging->numero_loja}\n";
echo "canal: {$staging->canal}\n";

echo "\n=== ITENS SALVOS ===\n";
echo json_encode($staging->itens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== DADOS ORIGINAIS - ITENS ===\n";
$itensOriginais = $staging->dados_originais['itens'] ?? [];
echo json_encode($itensOriginais, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
