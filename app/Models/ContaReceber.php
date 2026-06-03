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
        'estorno_pendente',
        'conta_bancaria_id',
        'categoria_id',
        'lote_recebimento_id',
        'transferencia_id',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ContaReceber $conta) {
            if ($conta->transferencia_id) {
                ContaPagar::where('transferencia_id', $conta->transferencia_id)->delete();
            }
        });
    }

    protected $casts = [
        'valor_parcela' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_recebimento' => 'date',
        'lancamento_manual' => 'boolean',
        'estorno_pendente' => 'boolean',
    ];

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class, 'id_venda', 'id_venda');
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaFinanceira::class, 'categoria_id');
    }

    public function loteRecebimento(): BelongsTo
    {
        return $this->belongsTo(LoteRecebimento::class, 'lote_recebimento_id');
    }
}
