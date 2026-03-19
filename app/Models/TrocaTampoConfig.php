<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrocaTampoConfig extends Model
{
    protected $table = 'troca_tampos_config';

    protected $fillable = [
        'grupo',
        'cor',
        'tipo_tampo',
        'sku_produto',
        'sku_tampo',
        'nome_produto',
        'nome_tampo',
        'cor_tampo',
        'familia_tampo',
    ];
}
