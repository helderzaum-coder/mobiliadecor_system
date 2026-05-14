<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdutoEstoque extends Model
{
    protected $table = 'produtos_estoque';

    protected static function booted(): void
    {
        static::saving(function (self $produto) {
            $produto->saldo = $produto->saldo_fisico + $produto->saldo_virtual;
        });
    }

    protected $fillable = [
        'sku',
        'nome',
        'formato',
        'saldo',
        'saldo_fisico',
        'saldo_virtual',
        'saldo_secondary',
        'saldo_minimo',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'saldo' => 'integer',
        'saldo_fisico' => 'integer',
        'saldo_virtual' => 'integer',
        'saldo_secondary' => 'integer',
        'saldo_minimo' => 'integer',
    ];

    /**
     * Recalcula saldo total a partir de físico + virtual.
     */
    public function recalcularSaldo(): int
    {
        $this->saldo = $this->saldo_fisico + $this->saldo_virtual;
        return $this->saldo;
    }

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
