<?php

namespace App\Policies;

use App\Models\ExtratoBancario;
use App\Models\User;

class ExtratoBancarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_extrato_bancario');
    }

    public function view(User $user, ExtratoBancario $extratoBancario): bool
    {
        return $user->hasPermissionTo('view_extrato_bancario');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_extrato_bancario');
    }

    public function update(User $user, ExtratoBancario $extratoBancario): bool
    {
        return $user->hasPermissionTo('update_extrato_bancario');
    }

    public function delete(User $user, ExtratoBancario $extratoBancario): bool
    {
        return $user->hasPermissionTo('delete_extrato_bancario');
    }
}
