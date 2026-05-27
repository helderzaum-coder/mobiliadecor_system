<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos_estoque', function (Blueprint $table) {
            $table->integer('saldo_carcaca')->default(0)->after('saldo_virtual')
                ->comment('Saldo real individual (carcaças físicas deste SKU específico)');
        });
    }

    public function down(): void
    {
        Schema::table('produtos_estoque', function (Blueprint $table) {
            $table->dropColumn('saldo_carcaca');
        });
    }
};
