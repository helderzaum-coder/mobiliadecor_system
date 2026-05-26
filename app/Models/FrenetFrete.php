<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrenetFrete extends Model
{
    protected $table = 'frenet_fretes';

    protected $fillable = [
        'frenet_id',
        'data_envio',
        'etiqueta',
        'destinatario',
        'cidade_uf',
        'modalidade',
        'valor_frete',
        'status',
        'utilizado',
        'venda_id',
    ];

    protected $casts = [
        'valor_frete' => 'decimal:2',
        'utilizado'   => 'boolean',
        'data_envio'  => 'date',
    ];
}
