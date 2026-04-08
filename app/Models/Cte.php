<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cte extends Model
{
    protected $table = 'ctes';

    protected $fillable = [
        'numero_cte',
        'chave_cte',
        'chave_nfe',
        'valor_frete',
        'remetente',
        'destinatario',
        'transportadora',
        'arquivo',
        'utilizado',
        'venda_id',
    ];

    protected $casts = [
        'valor_frete' => 'decimal:2',
        'utilizado' => 'boolean',
    ];
}
