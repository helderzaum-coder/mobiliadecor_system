<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilha_ml_dados', function (Blueprint $table) {
            $table->id();
            $table->string('numero_venda')->index();
            $table->string('bling_account', 20)->default('primary');
            $table->decimal('receita_produtos', 12, 2)->default(0);
            $table->decimal('tarifa_venda', 12, 2)->default(0);
            $table->decimal('receita_envio', 12, 2)->default(0);
            $table->decimal('tarifas_envio', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('rebate', 12, 2)->default(0);
            $table->boolean('tem_rebate')->default(false);
            $table->timestamps();

            $table->unique(['numero_venda', 'bling_account']);
        });

        Schema::create('planilha_shopee_dados', function (Blueprint $table) {
            $table->id();
            $table->string('numero_pedido')->index();
            $table->decimal('taxa_comissao', 12, 2)->default(0);
            $table->decimal('taxa_servico', 12, 2)->default(0);
            $table->decimal('taxa_envio', 12, 2)->default(0);
            $table->decimal('total_taxas', 12, 2)->default(0);
            $table->json('dados_originais')->nullable();
            $table->timestamps();

            $table->unique(['numero_pedido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilha_ml_dados');
        Schema::dropIfExists('planilha_shopee_dados');
    }
};
