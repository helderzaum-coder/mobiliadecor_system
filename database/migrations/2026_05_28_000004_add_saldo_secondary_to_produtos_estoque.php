<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos_estoque', function (Blueprint $table) {
            $table->integer('saldo_secondary')->default(0)->after('saldo');
        });
    }

    public function down(): void
    {
        Schema::table('produtos_estoque', function (Blueprint $table) {
            $table->dropColumn('saldo_secondary');
        });
    }
};
