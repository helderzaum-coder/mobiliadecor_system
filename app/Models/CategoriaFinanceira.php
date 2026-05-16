<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaFinanceira extends Model
{
    protected $table = 'categorias_financeiras';

    protected $fillable = [
        'nome',
        'tipo',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
