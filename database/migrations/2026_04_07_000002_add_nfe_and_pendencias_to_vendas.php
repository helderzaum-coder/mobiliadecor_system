<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->string('nfe_chave_acesso', 50)->nullable()->after('numero_nota_fiscal');
            $table->decimal('nfe_valor', 10, 2)->nullable()->after('nfe_chave_acesso');
            $table->string('canal_nome', 100)->nullable()->after('id_canal');
            $table->boolean('planilha_processada')->default(false)->after('ml_shipping_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['nfe_chave_acesso', 'nfe_valor', 'canal_nome', 'planilha_processada']);
        });
    }
};
