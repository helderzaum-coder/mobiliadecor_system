<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venda extends Model
{
    protected $table = 'vendas';
    protected $primaryKey = 'id_venda';

    protected $fillable = [
        'numero_pedido_canal',
        'numero_nota_fiscal',
        'valor_total_venda',
        'valor_frete_cliente',
        'id_canal',
        'id_cnpj',
        'data_venda',
        'frete_pago',
        'margem_frete',
        'margem_produto',
        'margem_venda_total',
        'margem_contribuicao',
    ];

    protected $casts = [
        'valor_total_venda' => 'decimal:2',
        'valor_frete_cliente' => 'decimal:2',
        'data_venda' => 'date',
        'frete_pago' => 'boolean',
        'margem_frete' => 'decimal:2',
        'margem_produto' => 'decimal:2',
        'margem_venda_total' => 'decimal:2',
        'margem_contribuicao' => 'decimal:2',
    ];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(CanalVenda::class, 'id_canal', 'id_canal');
    }

    public function cnpj(): BelongsTo
    {
        return $this->belongsTo(Cnpj::class, 'id_cnpj', 'id_cnpj');
    }

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class, 'id_venda', 'id_venda');
    }
}
