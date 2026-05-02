<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pedidos_bling_staging MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pendente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pedidos_bling_staging MODIFY COLUMN status ENUM('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente'");
    }
};
