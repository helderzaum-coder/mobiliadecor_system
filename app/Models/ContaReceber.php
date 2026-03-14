<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaReceber extends Model
{
    protected $table = 'contas_receber';
    protected $primaryKey = 'id_conta_receber';

    protected $fillable = [
        'id_venda',
        'valor_parcela',
        'data_vencimento',
        'data_recebimento',
        'status',
        'numero_parcela',
        'total_parcelas',
        'forma_pagamento',
        'observacoes',
        'lancamento_manual',
    ];

    protected $casts = [
        'valor_parcela' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_recebimento' => 'date',
        'lancamento_manual' => 'boolean',
    ];

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class, 'id_venda', 'id_venda');
    }
}
