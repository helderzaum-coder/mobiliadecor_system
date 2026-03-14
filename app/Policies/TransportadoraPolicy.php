<?php

namespace App\Policies;

use App\Models\Transportadora;
use App\Models\User;

class TransportadoraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_transportadora');
    }

    public function view(User $user, Transportadora $transportadora): bool
    {
        return $user->hasPermissionTo('view_transportadora');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_transportadora');
    }

    public function update(User $user, Transportadora $transportadora): bool
    {
        return $user->hasPermissionTo('update_transportadora');
    }

    public function delete(User $user, Transportadora $transportadora): bool
    {
        return $user->hasPermissionTo('delete_transportadora');
    }
}
