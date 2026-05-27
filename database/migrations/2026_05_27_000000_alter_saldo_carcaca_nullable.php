<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos_estoque', function (Blueprint $table) {
            $table->integer('saldo_carcaca')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('produtos_estoque', function (Blueprint $table) {
            $table->integer('saldo_carcaca')->default(0)->change();
        });
    }
};
