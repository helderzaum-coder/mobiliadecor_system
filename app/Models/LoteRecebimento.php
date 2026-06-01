<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoteRecebimento extends Model
{
    protected $table = 'lotes_recebimento';

    protected $fillable = [
        'data_recebimento',
        'descricao',
        'valor_total',
        'quantidade_contas',
    ];

    protected $casts = [
        'data_recebimento' => 'date',
        'valor_total' => 'decimal:2',
    ];

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class, 'lote_recebimento_id');
    }
}
