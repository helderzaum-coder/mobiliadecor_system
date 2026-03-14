<?php

namespace App\Policies;

use App\Models\Venda;
use App\Models\User;

class VendaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_venda');
    }

    public function view(User $user, Venda $venda): bool
    {
        return $user->hasPermissionTo('view_venda');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_venda');
    }

    public function update(User $user, Venda $venda): bool
    {
        return $user->hasPermissionTo('update_venda');
    }

    public function delete(User $user, Venda $venda): bool
    {
        return $user->hasPermissionTo('delete_venda');
    }
}
