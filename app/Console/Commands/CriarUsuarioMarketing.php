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

        $user = User::where('email', 'gptmobilia@gmail.com')->first();

        if ($user) {
            $user->update([
                'name' => 'Lucas',
                'password' => bcrypt('Lucas@2026'),
            ]);
        } else {
            $user = User::create([
                'name' => 'Lucas',
                'email' => 'gptmobilia@gmail.com',
                'password' => bcrypt('Lucas@2026'),
            ]);
        }

        $user->syncRoles(['marketing']);

        $this->info("Pronto!");
        $this->line("  Email: gptmobilia@gmail.com");
        $this->line("  Senha: Lucas@2026");
        $this->line("  Role: marketing");

        return 0;
    }
}
