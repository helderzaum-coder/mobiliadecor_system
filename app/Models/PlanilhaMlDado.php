<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanilhaMlDado extends Model
{
    protected $table = 'planilha_ml_dados';

    protected $fillable = [
        'numero_venda',
        'bling_account',
        'receita_produtos',
        'tarifa_venda',
        'receita_envio',
        'tarifas_envio',
        'total',
        'rebate',
        'tem_rebate',
    ];

    protected $casts = [
        'tem_rebate' => 'boolean',
        'receita_produtos' => 'decimal:2',
        'tarifa_venda' => 'decimal:2',
        'receita_envio' => 'decimal:2',
        'tarifas_envio' => 'decimal:2',
        'total' => 'decimal:2',
        'rebate' => 'decimal:2',
    ];
}
