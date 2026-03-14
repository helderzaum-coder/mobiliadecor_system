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
        'tipo_nota',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class, 'id_canal', 'id_canal');
    }

    public function regrasComissao(): HasMany
    {
        return $this->hasMany(RegraComissao::class, 'id_canal', 'id_canal');
    }
}
