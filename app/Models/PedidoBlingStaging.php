<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoBlingStaging extends Model
{
    protected $table = 'pedidos_bling_staging';

    protected $fillable = [
        'bling_id',
        'bling_account',
        'numero_pedido',
        'numero_loja',
        'data_pedido',
        'cliente_nome',
        'cliente_documento',
        'total_produtos',
        'total_pedido',
        'frete',
        'custo_frete',
        'comissao_calculada',
        'subsidio_pix',
        'base_imposto',
        'percentual_imposto',
        'valor_imposto',
        'canal',
        'nota_fiscal',
        'nfe_numero',
        'nfe_chave_acesso',
        'nfe_valor',
        'nfe_xml_url',
        'nfe_pdf_url',
        'situacao_id',
        'observacoes',
        'itens',
        'parcelas',
        'dados_originais',
        'status',
    ];

    protected $casts = [
        'data_pedido' => 'date',
        'total_produtos' => 'decimal:2',
        'total_pedido' => 'decimal:2',
        'frete' => 'decimal:2',
        'custo_frete' => 'decimal:2',
        'comissao_calculada' => 'decimal:2',
        'subsidio_pix' => 'decimal:2',
        'base_imposto' => 'decimal:2',
        'percentual_imposto' => 'decimal:2',
        'valor_imposto' => 'decimal:2',
        'nfe_valor' => 'decimal:2',
        'itens' => 'array',
        'parcelas' => 'array',
        'dados_originais' => 'array',
    ];
}
