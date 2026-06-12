<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canais_venda', function (Blueprint $table) {
            $table->decimal('percentual_antecipacao', 5, 2)->default(0)->after('imposto_sobre_frete');
        });
    }

    public function down(): void
    {
        Schema::table('canais_venda', function (Blueprint $table) {
            $table->dropColumn('percentual_antecipacao');
        });
    }
};
