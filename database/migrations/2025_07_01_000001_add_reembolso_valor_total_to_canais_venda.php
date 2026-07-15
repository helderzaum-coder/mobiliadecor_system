<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canais_venda', function (Blueprint $table) {
            $table->boolean('reembolso_valor_total')->default(false)->after('percentual_antecipacao');
        });
    }

    public function down(): void
    {
        Schema::table('canais_venda', function (Blueprint $table) {
            $table->dropColumn('reembolso_valor_total');
        });
    }
};
