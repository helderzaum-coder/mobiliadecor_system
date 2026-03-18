<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportadoraUf extends Model
{
    protected $table = 'transportadora_ufs';

    protected $fillable = [
        'id_transportadora',
        'uf',
    ];

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class, 'id_transportadora', 'id_transportadora');
    }
}
