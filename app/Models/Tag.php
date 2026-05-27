<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['nome', 'cor'];

    public function produtos(): BelongsToMany
    {
        return $this->belongsToMany(ProdutoEstoque::class, 'produto_estoque_tag', 'tag_id', 'produto_estoque_id');
    }
}
