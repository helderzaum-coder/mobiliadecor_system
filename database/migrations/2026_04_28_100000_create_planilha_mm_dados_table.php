<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilha_mm_dados', function (Blueprint $table) {
            $table->id();
            $table->string('numero_pedido')->index();
            $table->decimal('valor_original', 12, 2)->default(0);
            $table->decimal('percentual_desconto', 5, 2)->default(0);
            $table->decimal('valor_com_desconto', 12, 2)->default(0);
            $table->decimal('comissao', 12, 2)->default(0);
            $table->decimal('valor_pedido', 12, 2)->default(0);
            $table->string('tipo_pagamento', 50)->nullable();
            $table->json('dados_originais')->nullable();
            $table->timestamps();

            $table->unique(['numero_pedido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilha_mm_dados');
    }
};
