<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlingToken extends Model
{
    protected $fillable = [
        'account_key',
        'access_token',
        'refresh_token',
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
