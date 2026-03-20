<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Venda;
use App\Models\CanalVenda;
use App\Models\RegraComissao;
use App\Models\PedidoBlingStaging;

// Buscar a venda
$v = Venda::where('numero_pedido_canal', '2000015573442950')->first();
echo "=== VENDA ===\n";
echo "id_canal: {$v->id_canal}\n";
echo "subtotal: {$v->total_produtos}\n";
echo "custo_prod: {$v->custo_produtos}\n";
echo "comissao: {$v->comissao}\n";
echo "imposto: {$v->valor_imposto} ({$v->percentual_imposto}%)\n";
echo "base_imposto: {$v->base_imposto}\n";
echo "rebate: {$v->ml_valor_rebate}\n";
echo "sale_fee: {$v->ml_sale_fee}\n";
echo "tipo_anuncio: {$v->ml_tipo_anuncio}\n";
echo "tipo_frete: {$v->ml_tipo_frete}\n";
echo "frete_custo_ml: {$v->ml_frete_custo}\n";
echo "frete_receita_ml: {$v->ml_frete_receita}\n";

// Canal
$canal = CanalVenda::find($v->id_canal);
echo "\n=== CANAL ===\n";
echo "nome: {$canal->nome_canal}\n";
echo "tipo_nota: {$canal->tipo_nota}\n";
echo "comissao_sobre_frete: {$canal->comissao_sobre_frete}\n";
echo "imposto_sobre_frete: {$canal->imposto_sobre_frete}\n";

// Regras comissao
$regras = RegraComissao::where('id_canal', $v->id_canal)->where('ativo', true)->get();
echo "\n=== REGRAS COMISSAO ===\n";
foreach($regras as $r) {
    echo "regra: {$r->nome_regra} | %={$r->percentual} | fixo={$r->valor_fixo} | pix={$r->subsidio_pix} | ml_tipo={$r->ml_tipo_anuncio} | ml_frete={$r->ml_tipo_frete} | faixa_min={$r->faixa_valor_min} | faixa_max={$r->faixa_valor_max}\n";
}

// Staging
$staging = PedidoBlingStaging::where('numero_loja', '2000015573442950')->first();
if ($staging) {
    echo "\n=== STAGING ===\n";
    echo "comissao_calculada: {$staging->comissao_calculada}\n";
    echo "ml_sale_fee: {$staging->ml_sale_fee}\n";
    echo "ml_valor_rebate: {$staging->ml_valor_rebate}\n";
    echo "ml_tipo_anuncio: {$staging->ml_tipo_anuncio}\n";
    echo "ml_tipo_frete: {$staging->ml_tipo_frete}\n";
    echo "ml_frete_custo: {$staging->ml_frete_custo}\n";
    echo "ml_frete_receita: {$staging->ml_frete_receita}\n";
}
