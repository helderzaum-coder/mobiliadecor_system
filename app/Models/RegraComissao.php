<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegraComissao extends Model
{
    protected $table = 'regras_comissao';

    protected $fillable = [
        'id_canal',
        'nome_regra',
        'ml_tipo_anuncio',
        'ml_tipo_frete',
        'descricao',
        'percentual',
        'valor_fixo',
        'faixa_valor_min',
        'faixa_valor_max',
        'subsidio_pix',
        'ativo',
    ];

    protected $casts = [
        'percentual' => 'decimal:2',
        'valor_fixo' => 'decimal:2',
        'faixa_valor_min' => 'decimal:2',
        'faixa_valor_max' => 'decimal:2',
        'subsidio_pix' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(CanalVenda::class, 'id_canal', 'id_canal');
    }
}
