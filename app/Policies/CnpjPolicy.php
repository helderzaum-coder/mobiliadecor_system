<?php

namespace App\Policies;

use App\Models\Cnpj;
use App\Models\User;

class CnpjPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_cnpj');
    }

    public function view(User $user, Cnpj $cnpj): bool
    {
        return $user->hasPermissionTo('view_cnpj');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_cnpj');
    }

    public function update(User $user, Cnpj $cnpj): bool
    {
        return $user->hasPermissionTo('update_cnpj');
    }

    public function delete(User $user, Cnpj $cnpj): bool
    {
        return $user->hasPermissionTo('delete_cnpj');
    }
}
