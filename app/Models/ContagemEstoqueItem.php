<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContagemEstoqueItem extends Model
{
    public $timestamps = false;
    protected $table = 'contagens_estoque_itens';

    protected $fillable = ['contagem_id', 'sku', 'nome', 'grupo_tampo', 'saldo_sistema', 'contagem', 'diferenca'];

    public function contagem(): BelongsTo
    {
        return $this->belongsTo(ContagemEstoque::class, 'contagem_id');
    }
}
