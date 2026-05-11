<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produto_estoque_id');
            $table->enum('tipo', ['entrada', 'saida', 'balanco']);
            $table->integer('quantidade');
            $table->integer('saldo_anterior');
            $table->integer('saldo_posterior');
            $table->string('origem', 50)->comment('manual, venda_primary, venda_secondary, importacao, sync');
            $table->string('referencia')->nullable()->comment('Nº pedido, obs, etc');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['produto_estoque_id', 'created_at']);
            $table->index('origem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
    }
};
