<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos_estoque', function (Blueprint $table) {
            $table->integer('saldo_fisico')->default(0)->after('saldo');
            $table->integer('saldo_virtual')->default(0)->after('saldo_fisico');
        });

        // Migrar saldo existente para saldo_virtual (assumindo que o estoque atual é virtual/dropshipping)
        DB::table('produtos_estoque')->update(['saldo_virtual' => DB::raw('saldo'), 'saldo_fisico' => 0]);
    }

    public function down(): void
    {
        Schema::table('produtos_estoque', function (Blueprint $table) {
            $table->dropColumn(['saldo_fisico', 'saldo_virtual']);
        });
    }
};
