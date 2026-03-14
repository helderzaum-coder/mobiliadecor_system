<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtratoBancario extends Model
{
    protected $table = 'extratos_bancarios';
    protected $primaryKey = 'id_extrato';

    protected $fillable = [
        'id_cnpj',
        'data_movimento',
        'descricao',
        'valor',
        'tipo_movimento',
        'saldo',
    ];

    protected $casts = [
        'data_movimento' => 'date',
        'valor' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function cnpj(): BelongsTo
    {
        return $this->belongsTo(Cnpj::class, 'id_cnpj', 'id_cnpj');
    }
}
