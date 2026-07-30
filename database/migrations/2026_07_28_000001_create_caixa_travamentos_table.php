<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caixa_travamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conta_bancaria_id')->nullable()->comment('null = todas as contas');
            $table->date('data_travamento')->comment('Movimentações até esta data estão travadas');
            $table->string('observacao')->nullable();
            $table->unsignedBigInteger('criado_por')->nullable();
            $table->timestamps();

            $table->foreign('conta_bancaria_id')->references('id')->on('contas_bancarias')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_travamentos');
    }
};
