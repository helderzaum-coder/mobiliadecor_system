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
        'numero_nfe',
        'valor_frete',
        'remetente',
        'rem_documento',
        'destinatario',
        'dest_documento',
        'transportadora',
        'data_emissao',
        'arquivo',
        'utilizado',
        'venda_id',
        'tipo',
    ];

    protected $casts = [
        'valor_frete' => 'decimal:2',
        'utilizado' => 'boolean',
        'data_emissao' => 'date',
    ];
}
