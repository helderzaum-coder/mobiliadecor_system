<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_receber', function (Blueprint $table) {
            $table->id('id_conta_receber');
            $table->unsignedBigInteger('id_venda');
            $table->decimal('valor_parcela', 10, 2);
            $table->date('data_vencimento');
            $table->date('data_recebimento')->nullable();
            $table->string('status', 20);
            $table->integer('numero_parcela');
            $table->integer('total_parcelas');
            $table->string('forma_pagamento', 50);
            $table->text('observacoes')->nullable();
            $table->boolean('lancamento_manual')->default(false);
            $table->timestamps();

            $table->foreign('id_venda')->references('id_venda')->on('vendas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_receber');
    }
};
