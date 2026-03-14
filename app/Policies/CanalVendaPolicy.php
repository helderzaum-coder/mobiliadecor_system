<?php

namespace App\Policies;

use App\Models\CanalVenda;
use App\Models\User;

class CanalVendaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_canal_venda');
    }

    public function view(User $user, CanalVenda $canalVenda): bool
    {
        return $user->hasPermissionTo('view_canal_venda');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_canal_venda');
    }

    public function update(User $user, CanalVenda $canalVenda): bool
    {
        return $user->hasPermissionTo('update_canal_venda');
    }

    public function delete(User $user, CanalVenda $canalVenda): bool
    {
        return $user->hasPermissionTo('delete_canal_venda');
    }
}
