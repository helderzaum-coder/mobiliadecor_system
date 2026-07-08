<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFavorite extends Model
{
    protected $fillable = ['user_id', 'url', 'label', 'icon', 'sort_order'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
