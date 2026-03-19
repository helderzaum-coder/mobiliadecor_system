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
        'aplica_icms',
        'cobertura_completa',
        'taxa_despacho',
        'pedagio_fracao_kg',
        'pedagio_valor',
        'adv_percentual',
        'adv_minimo',
        'gris_percentual',
        'gris_minimo',
        'trt_valor',
        'tas_valor',
        'tda_valor',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'aplica_icms' => 'boolean',
        'cobertura_completa' => 'boolean',
        'taxa_despacho' => 'decimal:2',
        'pedagio_fracao_kg' => 'decimal:2',
        'pedagio_valor' => 'decimal:2',
        'adv_percentual' => 'decimal:4',
        'adv_minimo' => 'decimal:2',
        'gris_percentual' => 'decimal:4',
        'gris_minimo' => 'decimal:2',
        'trt_valor' => 'decimal:2',
        'tas_valor' => 'decimal:2',
        'tda_valor' => 'decimal:2',
    ];

    public function faturas(): HasMany
    {
        return $this->hasMany(FaturaTransportadora::class, 'id_transportadora', 'id_transportadora');
    }

    public function ufsAtendidas(): HasMany
    {
        return $this->hasMany(TransportadoraUf::class, 'id_transportadora', 'id_transportadora');
    }

    public function tabelaFrete(): HasMany
    {
        return $this->hasMany(TransportadoraTabelaFrete::class, 'id_transportadora', 'id_transportadora');
    }

    public function taxas(): HasMany
    {
        return $this->hasMany(TransportadoraTaxa::class, 'id_transportadora', 'id_transportadora');
    }
}
