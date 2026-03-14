<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canais_venda', function (Blueprint $table) {
            $table->id('id_canal');
            $table->string('nome_canal', 100);
            $table->decimal('percentual_comissao', 5, 2);
            $table->decimal('percentual_imposto', 5, 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canais_venda');
    }
};
