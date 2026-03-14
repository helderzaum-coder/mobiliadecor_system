<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canais_venda', function (Blueprint $table) {
            $table->boolean('comissao_sobre_frete')->default(false)->after('tipo_nota');
            $table->boolean('imposto_sobre_frete')->default(false)->after('comissao_sobre_frete');
        });
    }

    public function down(): void
    {
        Schema::table('canais_venda', function (Blueprint $table) {
            $table->dropColumn(['comissao_sobre_frete', 'imposto_sobre_frete']);
        });
    }
};
