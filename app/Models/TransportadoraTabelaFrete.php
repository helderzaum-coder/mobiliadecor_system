<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportadoraTabelaFrete extends Model
{
    protected $table = 'transportadora_tabela_frete';

    protected $fillable = [
        'id_transportadora',
        'uf',
        'cep_inicio',
        'cep_fim',
        'regiao',
        'peso_min',
        'peso_max',
        'valor_kg',
        'valor_fixo',
        'frete_minimo',
        'despacho',
        'pedagio_valor',
        'pedagio_fracao_kg',
        'adv_percentual',
        'adv_minimo',
        'gris_percentual',
        'gris_minimo',
    ];

    protected $casts = [
        'peso_min' => 'decimal:3',
        'peso_max' => 'decimal:3',
        'valor_kg' => 'decimal:2',
        'valor_fixo' => 'decimal:2',
        'frete_minimo' => 'decimal:2',
        'despacho' => 'decimal:2',
        'pedagio_valor' => 'decimal:2',
        'pedagio_fracao_kg' => 'decimal:2',
        'adv_percentual' => 'decimal:4',
        'adv_minimo' => 'decimal:2',
        'gris_percentual' => 'decimal:4',
        'gris_minimo' => 'decimal:2',
    ];

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class, 'id_transportadora', 'id_transportadora');
    }
}
