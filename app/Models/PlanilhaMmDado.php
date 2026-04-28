<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanilhaMmDado extends Model
{
    protected $table = 'planilha_mm_dados';

    protected $fillable = [
        'numero_pedido',
        'valor_original',
        'percentual_desconto',
        'valor_com_desconto',
        'comissao',
        'valor_pedido',
        'tipo_pagamento',
        'dados_originais',
    ];

    protected $casts = [
        'valor_original' => 'decimal:2',
        'percentual_desconto' => 'decimal:2',
        'valor_com_desconto' => 'decimal:2',
        'comissao' => 'decimal:2',
        'valor_pedido' => 'decimal:2',
        'dados_originais' => 'array',
    ];
}
