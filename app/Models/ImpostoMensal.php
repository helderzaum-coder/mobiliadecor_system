<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpostoMensal extends Model
{
    protected $table = 'impostos_mensais';
    protected $primaryKey = 'id_imposto';

    protected $fillable = [
        'id_cnpj',
        'mes_referencia',
        'ano_referencia',
        'percentual_imposto',
        'data_atualizacao',
    ];

    protected $casts = [
        'percentual_imposto' => 'decimal:2',
        'data_atualizacao' => 'date',
    ];

    public function cnpj(): BelongsTo
    {
        return $this->belongsTo(Cnpj::class, 'id_cnpj', 'id_cnpj');
    }
}
