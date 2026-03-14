<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaturaTransportadora extends Model
{
    protected $table = 'faturas_transportadoras';
    protected $primaryKey = 'id_fatura';

    protected $fillable = [
        'id_transportadora',
        'numero_fatura',
        'data_emissao',
        'valor_total',
        'data_vencimento',
    ];

    protected $casts = [
        'data_emissao' => 'date',
        'valor_total' => 'decimal:2',
        'data_vencimento' => 'date',
    ];

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class, 'id_transportadora', 'id_transportadora');
    }

    public function contasPagar(): HasMany
    {
        return $this->hasMany(ContaPagar::class, 'id_fatura', 'id_fatura');
    }
}
