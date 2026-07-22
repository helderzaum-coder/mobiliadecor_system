<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclamacoes_ml', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_venda')->nullable();
            $table->string('numero_pedido')->nullable();
            $table->decimal('valor', 10, 2);
            $table->date('data_abertura');
            $table->date('data_resolucao')->nullable();
            $table->enum('status', ['aberta', 'liberada', 'estornada'])->default('aberta');
            $table->string('motivo')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('conta_bancaria_id')->nullable();
            $table->unsignedBigInteger('conta_pagar_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamacoes_ml');
    }
};
