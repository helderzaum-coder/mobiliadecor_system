<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContaBancaria extends Model
{
    protected $table = 'contas_bancarias';

    protected $fillable = [
        'nome',
        'banco',
        'agencia',
        'conta',
        'saldo_inicial',
        'ativo',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'ativo' => 'boolean',
    ];
}
