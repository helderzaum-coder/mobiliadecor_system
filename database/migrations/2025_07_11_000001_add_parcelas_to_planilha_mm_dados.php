<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilha_mm_dados', function (Blueprint $table) {
            $table->unsignedTinyInteger('parcelas')->default(1)->after('tipo_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('planilha_mm_dados', function (Blueprint $table) {
            $table->dropColumn('parcelas');
        });
    }
};
