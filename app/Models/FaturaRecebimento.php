<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaturaRecebimento extends Model
{
    protected $table = 'faturas_recebimento';

    protected $fillable = [
        'canal_id',
        'descricao',
        'data_prevista',
        'status',
        'valor_total',
        'conta_bancaria_id',
        'lote_recebimento_id',
        'descontos',
        'entradas_avulsas',
    ];

    protected $casts = [
        'data_prevista' => 'date',
        'valor_total' => 'decimal:2',
        'descontos' => 'array',
        'entradas_avulsas' => 'array',
    ];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(CanalVenda::class, 'canal_id', 'id_canal');
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    public function loteRecebimento(): BelongsTo
    {
        return $this->belongsTo(LoteRecebimento::class, 'lote_recebimento_id');
    }

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class, 'fatura_recebimento_id');
    }
}
