<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReclamacaoML extends Model
{
    protected $table = 'reclamacoes_ml';

    protected $fillable = [
        'id_venda',
        'numero_pedido',
        'valor',
        'data_abertura',
        'data_resolucao',
        'status',
        'motivo',
        'observacoes',
        'conta_bancaria_id',
        'conta_pagar_id',
    ];

    protected $casts = [
        'valor'           => 'decimal:2',
        'data_abertura'   => 'date',
        'data_resolucao'  => 'date',
    ];

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class, 'id_venda', 'id_venda');
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    public function contaPagar(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_id', 'id_conta_pagar');
    }
}
