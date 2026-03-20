<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Venda;
use App\Models\CanalVenda;

// Corrigir vendas ML existentes usando dados reais da API
$vendas = Venda::whereHas('canal', function($q) {
    $q->where('nome_canal', 'like', '%ercado%');
})->get();

echo $vendas->count() . " vendas ML encontradas\n";

foreach ($vendas as $v) {
    $canal = CanalVenda::find($v->id_canal);
    $tipoFrete = $v->ml_tipo_frete;
    $mlSaleFee = (float) ($v->ml_sale_fee ?? 0);
    $mlFreteCusto = (float) ($v->ml_frete_custo ?? 0);
    $mlFreteReceita = (float) ($v->ml_frete_receita ?? 0);

    $totalProdutos = (float) $v->total_produtos;
    $custoProdutos = (float) $v->custo_produtos;
    $valorImposto = (float) $v->valor_imposto;
    $totalPedido = (float) $v->valor_total_venda;
    $valorRebate = (float) ($v->ml_valor_rebate ?? 0);
    $subsidioPix = (float) $v->subsidio_pix;

    $comissao = (float) $v->comissao;
    $frete = (float) $v->valor_frete_cliente;
    $custoFrete = (float) $v->valor_frete_transportadora;

    if ($tipoFrete === 'ME2' || $tipoFrete === 'FULL') {
        $taxaFreteML = $mlFreteCusto > 0 ? ($mlFreteCusto - $mlFreteReceita) : 0;
        if ($mlSaleFee > 0) {
            $comissao = $mlSaleFee + $taxaFreteML;
        }
        $frete = 0;
        $custoFrete = 0;
    } elseif ($tipoFrete === 'ME1') {
        if ($mlFreteReceita > 0) $frete = $mlFreteReceita;
        if ($mlFreteCusto > 0) $custoFrete = $mlFreteCusto;
        if ($mlSaleFee > 0) $comissao = $mlSaleFee;
    }

    $margemFrete = $frete - $custoFrete;
    $comissaoProduto = $comissao;
    $impostoProduto = $valorImposto;
    $margemProduto = $totalProdutos - $custoProdutos - $comissaoProduto - $impostoProduto + $valorRebate;
    $margemVendaTotal = $margemProduto + $margemFrete + $subsidioPix;
    $margemContribuicao = $totalPedido > 0 ? round(($margemVendaTotal / $totalPedido) * 100, 2) : 0;

    $v->update([
        'comissao' => round($comissao, 2),
        'valor_frete_cliente' => round($frete, 2),
        'valor_frete_transportadora' => round($custoFrete, 2),
        'margem_frete' => round($margemFrete, 2),
        'margem_produto' => round($margemProduto, 2),
        'margem_venda_total' => round($margemVendaTotal, 2),
        'margem_contribuicao' => round($margemContribuicao, 2),
    ]);

    echo $v->numero_pedido_canal . " | tipo={$tipoFrete} | comissao=" . round($comissao, 2) . " | margem=" . round($margemVendaTotal, 2) . "\n";
}
echo "DONE\n";
