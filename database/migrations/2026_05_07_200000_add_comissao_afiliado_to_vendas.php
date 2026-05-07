<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->decimal('comissao_afiliado', 12, 2)->default(0)->after('comissao');
            $table->boolean('planilha_afiliado_processada')->default(false)->after('planilha_processada');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['comissao_afiliado', 'planilha_afiliado_processada']);
        });
    }
};
