<?php

namespace App\Policies;

use App\Models\FaturaTransportadora;
use App\Models\User;

class FaturaTransportadoraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_fatura_transportadora');
    }

    public function view(User $user, FaturaTransportadora $faturaTransportadora): bool
    {
        return $user->hasPermissionTo('view_fatura_transportadora');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_fatura_transportadora');
    }

    public function update(User $user, FaturaTransportadora $faturaTransportadora): bool
    {
        return $user->hasPermissionTo('update_fatura_transportadora');
    }

    public function delete(User $user, FaturaTransportadora $faturaTransportadora): bool
    {
        return $user->hasPermissionTo('delete_fatura_transportadora');
    }
}
