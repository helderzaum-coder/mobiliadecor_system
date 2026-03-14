<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanalVenda extends Model
{
    protected $table = 'canais_venda';
    protected $primaryKey = 'id_canal';

    protected $fillable = [
        'nome_canal',
        'percentual_comissao',
        'percentual_imposto',
        'ativo',
    ];

    protected $casts = [
        'percentual_comissao' => 'decimal:2',
        'percentual_imposto' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class, 'id_canal', 'id_canal');
    }
}
