<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regras_comissao', function (Blueprint $table) {
            $table->string('ml_tipo_anuncio')->nullable()->after('nome_regra');
            $table->string('ml_tipo_frete')->nullable()->after('ml_tipo_anuncio');
        });
    }

    public function down(): void
    {
        Schema::table('regras_comissao', function (Blueprint $table) {
            $table->dropColumn(['ml_tipo_anuncio', 'ml_tipo_frete']);
        });
    }
};
