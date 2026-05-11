<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos_estoque', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique();
            $table->string('nome');
            $table->string('formato', 10)->default('S')->comment('S=Simples, E=Kit/Estrutura');
            $table->integer('saldo')->default(0);
            $table->integer('saldo_minimo')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Componentes de kits
        Schema::create('produto_estoque_componentes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kit_id');
            $table->unsignedBigInteger('componente_id');
            $table->integer('quantidade')->default(1);
            $table->timestamps();

            $table->unique(['kit_id', 'componente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_estoque_componentes');
        Schema::dropIfExists('produtos_estoque');
    }
};
