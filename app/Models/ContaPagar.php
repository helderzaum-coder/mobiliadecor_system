<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaPagar extends Model
{
    protected $table = 'contas_pagar';
    protected $primaryKey = 'id_conta_pagar';

    protected $fillable = [
        'id_fatura',
        'valor_parcela',
        'data_vencimento',
        'data_pagamento',
        'status',
        'numero_parcela',
        'total_parcelas',
        'forma_pagamento',
        'observacoes',
        'lancamento_manual',
        'conta_bancaria_id',
        'categoria_id',
        'lote_recebimento_id',
    ];

    protected $casts = [
        'valor_parcela' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
        'lancamento_manual' => 'boolean',
    ];

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(FaturaTransportadora::class, 'id_fatura', 'id_fatura');
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
