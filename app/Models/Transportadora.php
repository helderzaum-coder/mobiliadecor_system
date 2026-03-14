<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transportadora extends Model
{
    protected $table = 'transportadoras';
    protected $primaryKey = 'id_transportadora';

    protected $fillable = [
        'nome_transportadora',
        'cnpj',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function faturas(): HasMany
    {
        return $this->hasMany(FaturaTransportadora::class, 'id_transportadora', 'id_transportadora');
    }
}
