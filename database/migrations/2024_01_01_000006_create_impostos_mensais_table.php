<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impostos_mensais', function (Blueprint $table) {
            $table->id('id_imposto');
            $table->unsignedBigInteger('id_cnpj');
            $table->integer('mes_referencia');
            $table->integer('ano_referencia');
            $table->decimal('percentual_imposto', 5, 2);
            $table->date('data_atualizacao');
            $table->timestamps();

            $table->foreign('id_cnpj')->references('id_cnpj')->on('cnpjs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impostos_mensais');
    }
};
