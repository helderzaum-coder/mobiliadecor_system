<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE contas_pagar MODIFY COLUMN id_fatura INT NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE contas_pagar MODIFY COLUMN id_fatura INT NOT NULL");
    }
};
