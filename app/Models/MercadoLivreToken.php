<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercadoLivreToken extends Model
{
    protected $table = 'mercadolivre_tokens';

    protected $fillable = [
        'account_key',
        'access_token',
        'refresh_token',
        'user_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return now()->gte($this->expires_at->subMinutes(5));
    }
}
