<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_bling_staging', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bling_id')->unique();
            $table->string('bling_account', 50);
            $table->integer('numero_pedido');
            $table->string('numero_loja', 100)->nullable();
            $table->date('data_pedido');
            $table->string('cliente_nome', 191)->nullable();
            $table->string('cliente_documento', 20)->nullable();
            $table->decimal('total_produtos', 10, 2);
            $table->decimal('total_pedido', 10, 2);
            $table->decimal('frete', 10, 2)->default(0);
            $table->decimal('custo_frete', 10, 2)->default(0);
            $table->string('canal', 100)->nullable();
            $table->string('nota_fiscal', 50)->nullable();
            $table->integer('situacao_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->json('itens')->nullable();
            $table->json('parcelas')->nullable();
            $table->json('dados_originais')->nullable();
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_bling_staging');
    }
};
