<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contagens_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->integer('total_itens')->default(0);
            $table->integer('com_divergencia')->default(0);
            $table->integer('sem_alteracao')->default(0);
            $table->timestamps();
        });

        Schema::create('contagens_estoque_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contagem_id')->constrained('contagens_estoque')->cascadeOnDelete();
            $table->string('sku');
            $table->string('nome');
            $table->string('grupo_tampo')->nullable();
            $table->integer('saldo_sistema');
            $table->integer('contagem');
            $table->integer('diferenca');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contagens_estoque_itens');
        Schema::dropIfExists('contagens_estoque');
    }
};
