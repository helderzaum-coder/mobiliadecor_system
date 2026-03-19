<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanilhaShopeeDado extends Model
{
    protected $table = 'planilha_shopee_dados';

    protected $fillable = [
        'numero_pedido',
        'taxa_comissao',
        'taxa_servico',
        'taxa_envio',
        'total_taxas',
        'dados_originais',
    ];

    protected $casts = [
        'taxa_comissao' => 'decimal:2',
        'taxa_servico' => 'decimal:2',
        'taxa_envio' => 'decimal:2',
        'total_taxas' => 'decimal:2',
        'dados_originais' => 'array',
    ];
}
