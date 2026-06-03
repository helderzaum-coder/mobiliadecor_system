<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ContaPagar extends Model
{
    protected $table = 'contas_pagar';
    protected $primaryKey = 'id_conta_pagar';

    protected $fillable = [
        'id_fatura',
        'descricao',
        'valor_parcela',
        'data_vencimento',
        'data_pagamento',
        'data_lancamento',
        'status',
        'numero_parcela',
        'total_parcelas',
        'recorrente',
        'intervalo_recorrencia',
        'data_fim_recorrencia',
        'juros_atraso',
        'tipo_juros',
        'grupo_recorrencia',
        'forma_pagamento',
        'observacoes',
        'lancamento_manual',
        'conta_bancaria_id',
        'categoria_id',
        'lote_recebimento_id',
    ];

    protected $casts = [
        'valor_parcela' => 'decimal:2',
        'juros_atraso' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
        'data_lancamento' => 'date',
        'data_fim_recorrencia' => 'date',
        'lancamento_manual' => 'boolean',
        'recorrente' => 'boolean',
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

    /**
     * Calcula valor atualizado com juros de atraso.
     */
    public function getValorAtualizadoAttribute(): float
    {
        if ($this->status === 'pago' || !$this->juros_atraso || !$this->data_vencimento) {
            return (float) $this->valor_parcela;
        }

        $hoje = Carbon::today();
        $vencimento = $this->data_vencimento;

        if ($hoje->lte($vencimento)) {
            return (float) $this->valor_parcela;
        }

        $diasAtraso = $vencimento->diffInDays($hoje);
        $percentual = $this->tipo_juros === 'ao_mes'
            ? ($this->juros_atraso / 30) * $diasAtraso
            : $this->juros_atraso * $diasAtraso;

        return round((float) $this->valor_parcela * (1 + $percentual / 100), 2);
    }

    public function getDiasAtrasoAttribute(): int
    {
        if ($this->status === 'pago' || !$this->data_vencimento) {
            return 0;
        }

        $hoje = Carbon::today();
        return $hoje->gt($this->data_vencimento) ? $this->data_vencimento->diffInDays($hoje) : 0;
    }
}
