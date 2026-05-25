<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->string('tipo', 20)->default('entrega')->after('venda_id');
        });

        // Todos os CT-es existentes = entrega
        DB::table('ctes')->update(['tipo' => 'entrega']);

        // Vendas com frete_pago manual (sem CT-e vinculado) já são consideradas "entrega" implicitamente
    }

    public function down(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
