<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportadora_tabela_frete', function (Blueprint $table) {
            $table->decimal('peso_min', 12, 3)->change();
            $table->decimal('peso_max', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transportadora_tabela_frete', function (Blueprint $table) {
            $table->decimal('peso_min', 10, 3)->change();
            $table->decimal('peso_max', 10, 3)->change();
        });
    }
};
