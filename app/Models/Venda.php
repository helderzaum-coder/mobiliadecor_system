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
        'bling_id',
        'bling_account',
        'numero_pedido_canal',
        'numero_nota_fiscal',
        'valor_total_venda',
        'total_produtos',
        'custo_produtos',
        'valor_frete_cliente',
        'valor_frete_transportadora',
        'comissao',
        'subsidio_pix',
        'base_imposto',
        'percentual_imposto',
        'valor_imposto',
        'id_canal',
        'id_cnpj',
        'data_venda',
        'cliente_nome',
        'cliente_documento',
        'frete_pago',
        'observacoes',
        'bling_situacao_id',
        'margem_frete',
        'margem_produto',
        'margem_venda_total',
        'margem_contribuicao',
        'ml_tipo_anuncio',
        'ml_tipo_frete',
        'ml_tem_rebate',
        'ml_valor_rebate',
        'ml_sale_fee',
        'ml_frete_custo',
        'ml_frete_receita',
        'ml_order_id',
        'ml_shipping_id',
    ];

    protected $casts = [
        'valor_total_venda' => 'decimal:2',
        'total_produtos' => 'decimal:2',
        'custo_produtos' => 'decimal:2',
        'valor_frete_cliente' => 'decimal:2',
        'valor_frete_transportadora' => 'decimal:2',
        'comissao' => 'decimal:2',
        'subsidio_pix' => 'decimal:2',
        'base_imposto' => 'decimal:2',
        'percentual_imposto' => 'decimal:2',
        'valor_imposto' => 'decimal:2',
        'data_venda' => 'date',
        'frete_pago' => 'boolean',
        'margem_frete' => 'decimal:2',
        'margem_produto' => 'decimal:2',
        'margem_venda_total' => 'decimal:2',
        'margem_contribuicao' => 'decimal:2',
        'ml_tem_rebate' => 'boolean',
        'ml_valor_rebate' => 'decimal:2',
        'ml_sale_fee' => 'decimal:2',
        'ml_frete_custo' => 'decimal:2',
        'ml_frete_receita' => 'decimal:2',
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
