<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CriarUsuarioMarketing extends Command
{
    protected $signature = 'user:criar-marketing';
    protected $description = 'Cria o usuário marketing com acesso à Calculadora Marketplace';

    public function handle(): int
    {
        Role::firstOrCreate(['name' => 'marketing']);

        $user = User::firstOrCreate(
            ['email' => 'marketing@mobiliadecor.com.br'],
            [
                'name' => 'Lucas',
                'password' => bcrypt('Lucas@2026'),
            ]
        );

        $user->syncRoles(['marketing']);

        $this->info("Usuário criado:");
        $this->line("  Email: marketing@mobiliadecor.com.br");
        $this->line("  Senha: Lucas@2026");
        $this->line("  Role: marketing");
        $this->warn("⚠ Peça ao Lucas para trocar a senha no primeiro acesso.");

        return 0;
    }
}
