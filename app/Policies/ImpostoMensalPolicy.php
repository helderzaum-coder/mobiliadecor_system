<?php

namespace App\Policies;

use App\Models\ImpostoMensal;
use App\Models\User;

class ImpostoMensalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_imposto_mensal');
    }

    public function view(User $user, ImpostoMensal $impostoMensal): bool
    {
        return $user->hasPermissionTo('view_imposto_mensal');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_imposto_mensal');
    }

    public function update(User $user, ImpostoMensal $impostoMensal): bool
    {
        return $user->hasPermissionTo('update_imposto_mensal');
    }

    public function delete(User $user, ImpostoMensal $impostoMensal): bool
    {
        return $user->hasPermissionTo('delete_imposto_mensal');
    }
}
