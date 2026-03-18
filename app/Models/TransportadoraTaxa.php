<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportadoraTaxa extends Model
{
    protected $table = 'transportadora_taxas';

    protected $fillable = [
        'id_transportadora',
        'tipo_taxa',
        'uf',
        'cidade',
        'cep_inicio',
        'cep_fim',
        'valor_fixo',
        'percentual',
        'observacao',
    ];

    protected $casts = [
        'valor_fixo' => 'decimal:2',
        'percentual' => 'decimal:4',
    ];

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class, 'id_transportadora', 'id_transportadora');
    }
}
