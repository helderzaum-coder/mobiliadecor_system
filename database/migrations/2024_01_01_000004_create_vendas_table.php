<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas', function (Blueprint $table) {
            $table->id('id_venda');
            $table->string('numero_pedido_canal', 50);
            $table->string('numero_nota_fiscal', 50);
            $table->decimal('valor_total_venda', 10, 2);
            $table->decimal('valor_frete_cliente', 10, 2);
            $table->unsignedBigInteger('id_canal');
            $table->unsignedBigInteger('id_cnpj');
            $table->date('data_venda');
            $table->boolean('frete_pago')->default(false);
            $table->decimal('margem_frete', 10, 2)->nullable();
            $table->decimal('margem_produto', 10, 2)->nullable();
            $table->decimal('margem_venda_total', 10, 2)->nullable();
            $table->decimal('margem_contribuicao', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('id_canal')->references('id_canal')->on('canais_venda');
            $table->foreign('id_cnpj')->references('id_cnpj')->on('cnpjs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas');
    }
};
