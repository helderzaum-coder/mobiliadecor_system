<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas_transportadoras', function (Blueprint $table) {
            $table->id('id_fatura');
            $table->unsignedBigInteger('id_transportadora');
            $table->string('numero_fatura', 50);
            $table->date('data_emissao');
            $table->decimal('valor_total', 10, 2);
            $table->date('data_vencimento');
            $table->timestamps();

            $table->foreign('id_transportadora')->references('id_transportadora')->on('transportadoras');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas_transportadoras');
    }
};
