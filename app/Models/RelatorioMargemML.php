<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelatorioMargemML extends Model
{
    protected $table = 'relatorio_margem_ml';

    protected $fillable = [
        'account_key', 'mlb_id', 'sku', 'titulo', 'listing_type',
        'catalog_product_id', 'is_catalog_listing',
        'preco_venda', 'custo_produto', 'estoque',
        'comissao_pct', 'comissao_valor', 'frete',
        'imposto_pct', 'imposto_valor', 'margem_valor', 'margem_pct',
        'promocoes', 'preco_promocional', 'margem_promocional', 'margem_promocional_pct',
        'gerado_em',
    ];

    protected $casts = [
        'promocoes' => 'array',
        'gerado_em' => 'datetime',
    ];
}
