<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatorio_margem_ml', function (Blueprint $table) {
            $table->id();
            $table->string('account_key', 50);
            $table->string('mlb_id', 30)->index();
            $table->string('sku', 100)->nullable();
            $table->string('titulo')->nullable();
            $table->string('listing_type', 30)->nullable()->comment('gold_pro, gold_special');
            $table->string('catalog_product_id', 50)->nullable()->comment('MLBU do catálogo');
            $table->boolean('is_catalog_listing')->default(false);
            $table->decimal('preco_venda', 10, 2)->default(0);
            $table->decimal('custo_produto', 10, 2)->default(0);
            $table->integer('estoque')->default(0);
            $table->decimal('comissao_pct', 5, 2)->default(0);
            $table->decimal('comissao_valor', 10, 2)->default(0);
            $table->decimal('frete', 10, 2)->default(0);
            $table->decimal('imposto_pct', 5, 2)->default(0);
            $table->decimal('imposto_valor', 10, 2)->default(0);
            $table->decimal('margem_valor', 10, 2)->default(0);
            $table->decimal('margem_pct', 6, 2)->default(0);
            // Promoções
            $table->json('promocoes')->nullable()->comment('Array de promoções ativas');
            $table->decimal('preco_promocional', 10, 2)->nullable();
            $table->decimal('margem_promocional', 10, 2)->nullable();
            $table->decimal('margem_promocional_pct', 6, 2)->nullable();
            // Controle
            $table->timestamp('gerado_em')->useCurrent();
            $table->timestamps();

            $table->index(['account_key', 'gerado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatorio_margem_ml');
    }
};
