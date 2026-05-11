<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdutoEstoque extends Model
{
    protected $table = 'produtos_estoque';

    protected $fillable = [
        'sku',
        'nome',
        'formato',
        'saldo',
        'saldo_minimo',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'saldo' => 'integer',
        'saldo_minimo' => 'integer',
    ];

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class, 'produto_estoque_id');
    }

    public function componentes(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'produto_estoque_componentes', 'kit_id', 'componente_id')
            ->withPivot('quantidade')
            ->withTimestamps();
    }

    public function kits(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'produto_estoque_componentes', 'componente_id', 'kit_id')
            ->withPivot('quantidade')
            ->withTimestamps();
    }

    public function isKit(): bool
    {
        return in_array($this->formato, ['E', 'C']);
    }
}
