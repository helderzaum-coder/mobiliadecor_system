<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MlItemIgnorado extends Model
{
    protected $table = 'ml_itens_ignorados';

    protected $fillable = ['item_id', 'promotion_id', 'account_key'];
}
