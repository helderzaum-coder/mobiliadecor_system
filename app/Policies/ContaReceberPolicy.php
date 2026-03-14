<?php

namespace App\Policies;

use App\Models\ContaReceber;
use App\Models\User;

class ContaReceberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_conta_receber');
    }

    public function view(User $user, ContaReceber $contaReceber): bool
    {
        return $user->hasPermissionTo('view_conta_receber');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_conta_receber');
    }

    public function update(User $user, ContaReceber $contaReceber): bool
    {
        return $user->hasPermissionTo('update_conta_receber');
    }

    public function delete(User $user, ContaReceber $contaReceber): bool
    {
        return $user->hasPermissionTo('delete_conta_receber');
    }
}
