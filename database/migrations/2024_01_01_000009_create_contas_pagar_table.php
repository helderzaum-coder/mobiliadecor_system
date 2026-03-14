<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_pagar', function (Blueprint $table) {
            $table->id('id_conta_pagar');
            $table->unsignedBigInteger('id_fatura');
            $table->decimal('valor_parcela', 10, 2);
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->string('status', 20);
            $table->integer('numero_parcela');
            $table->integer('total_parcelas');
            $table->string('forma_pagamento', 50);
            $table->text('observacoes')->nullable();
            $table->boolean('lancamento_manual')->default(false);
            $table->timestamps();

            $table->foreign('id_fatura')->references('id_fatura')->on('faturas_transportadoras');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_pagar');
    }
};
