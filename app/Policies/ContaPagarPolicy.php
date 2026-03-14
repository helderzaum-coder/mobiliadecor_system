<?php

namespace App\Policies;

use App\Models\ContaPagar;
use App\Models\User;

class ContaPagarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_conta_pagar');
    }

    public function view(User $user, ContaPagar $contaPagar): bool
    {
        return $user->hasPermissionTo('view_conta_pagar');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_conta_pagar');
    }

    public function update(User $user, ContaPagar $contaPagar): bool
    {
        return $user->hasPermissionTo('update_conta_pagar');
    }

    public function delete(User $user, ContaPagar $contaPagar): bool
    {
        return $user->hasPermissionTo('delete_conta_pagar');
    }
}
