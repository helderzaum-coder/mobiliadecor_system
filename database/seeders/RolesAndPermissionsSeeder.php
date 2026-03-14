<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $modules = [
            'cnpj',
            'canal_venda',
            'transportadora',
            'venda',
            'conta_receber',
            'conta_pagar',
            'imposto_mensal',
            'extrato_bancario',
            'fatura_transportadora',
        ];

        $actions = ['view', 'create', 'update', 'delete'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$action}_{$module}"]);
            }
        }

        // Admin - acesso total
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        // Financeiro - vendas, contas, extratos
        $financeiro = Role::firstOrCreate(['name' => 'financeiro']);
        $financeiro->givePermissionTo([
            'view_venda', 'create_venda', 'update_venda',
            'view_conta_receber', 'create_conta_receber', 'update_conta_receber',
            'view_conta_pagar', 'create_conta_pagar', 'update_conta_pagar',
            'view_extrato_bancario', 'create_extrato_bancario', 'update_extrato_bancario',
            'view_canal_venda',
            'view_cnpj',
            'view_transportadora',
            'view_fatura_transportadora',
        ]);

        // Operacional - vendas e transporte
        $operacional = Role::firstOrCreate(['name' => 'operacional']);
        $operacional->givePermissionTo([
            'view_venda', 'create_venda', 'update_venda',
            'view_canal_venda',
            'view_cnpj',
            'view_transportadora',
            'view_fatura_transportadora', 'create_fatura_transportadora', 'update_fatura_transportadora',
        ]);

        // Visualizador - somente leitura
        $visualizador = Role::firstOrCreate(['name' => 'visualizador']);
        foreach ($modules as $module) {
            $visualizador->givePermissionTo("view_{$module}");
        }
    }
}
