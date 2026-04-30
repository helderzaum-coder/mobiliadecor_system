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

        $user = User::where('email', 'marketing@mobiliadecor.com.br')->first();

        if ($user) {
            $user->update([
                'name' => 'Lucas',
                'password' => bcrypt('Lucas@2026'),
            ]);
            $this->line("Usuário existente atualizado.");
        } else {
            $user = User::create([
                'name' => 'Lucas',
                'email' => 'marketing@mobiliadecor.com.br',
                'password' => bcrypt('Lucas@2026'),
            ]);
            $this->line("Usuário criado.");
        }

        $user->syncRoles(['marketing']);

        $this->info("Pronto!");
        $this->line("  Email: marketing@mobiliadecor.com.br");
        $this->line("  Senha: Lucas@2026");
        $this->line("  Role: marketing");
        $this->warn("⚠ Peça ao Lucas para trocar a senha no primeiro acesso.");

        return 0;
    }
}
