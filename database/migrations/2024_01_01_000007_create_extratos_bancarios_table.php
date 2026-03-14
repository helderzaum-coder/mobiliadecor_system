<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extratos_bancarios', function (Blueprint $table) {
            $table->id('id_extrato');
            $table->unsignedBigInteger('id_cnpj');
            $table->date('data_movimento');
            $table->string('descricao', 191);
            $table->decimal('valor', 10, 2);
            $table->string('tipo_movimento', 50);
            $table->decimal('saldo', 10, 2);
            $table->timestamps();

            $table->foreign('id_cnpj')->references('id_cnpj')->on('cnpjs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extratos_bancarios');
    }
};
