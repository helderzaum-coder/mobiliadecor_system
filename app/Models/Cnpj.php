<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cnpj extends Model
{
    protected $table = 'cnpjs';
    protected $primaryKey = 'id_cnpj';

    protected $fillable = [
        'numero_cnpj',
        'razao_social',
        'regime_tributario',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class, 'id_cnpj', 'id_cnpj');
    }

    public function impostosMensais(): HasMany
    {
        return $this->hasMany(ImpostoMensal::class, 'id_cnpj', 'id_cnpj');
    }

    public function extratosBancarios(): HasMany
    {
        return $this->hasMany(ExtratoBancario::class, 'id_cnpj', 'id_cnpj');
    }
}
