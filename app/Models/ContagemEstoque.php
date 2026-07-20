<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContagemEstoque extends Model
{
    protected $table = 'contagens_estoque';

    protected $fillable = ['user_id', 'total_itens', 'com_divergencia', 'sem_alteracao'];

    public function itens(): HasMany
    {
        return $this->hasMany(ContagemEstoqueItem::class, 'contagem_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
