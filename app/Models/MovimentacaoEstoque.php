<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentacaoEstoque extends Model
{
    protected $table = 'movimentacoes_estoque';

    protected $fillable = [
        'produto_estoque_id',
        'tipo',
        'quantidade',
        'saldo_anterior',
        'saldo_posterior',
        'origem',
        'referencia',
        'user_id',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'saldo_anterior' => 'integer',
        'saldo_posterior' => 'integer',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(ProdutoEstoque::class, 'produto_estoque_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
