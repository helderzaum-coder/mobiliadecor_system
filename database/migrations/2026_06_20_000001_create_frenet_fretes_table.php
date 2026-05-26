<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frenet_fretes', function (Blueprint $table) {
            $table->id();
            $table->string('frenet_id')->unique();
            $table->date('data_envio')->nullable();
            $table->string('etiqueta')->nullable();
            $table->string('destinatario');
            $table->string('cidade_uf')->nullable();
            $table->string('modalidade')->nullable();
            $table->decimal('valor_frete', 10, 2)->default(0);
            $table->string('status')->nullable();
            $table->string('tipo')->default('entrega');
            $table->boolean('utilizado')->default(false);
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frenet_fretes');
    }
};
