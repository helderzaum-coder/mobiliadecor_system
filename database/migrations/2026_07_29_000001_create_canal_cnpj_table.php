<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canal_cnpj', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_canal');
            $table->unsignedBigInteger('cnpj_id');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->foreign('id_canal')->references('id_canal')->on('canais_venda')->onDelete('cascade');
            $table->foreign('cnpj_id')->references('id_cnpj')->on('cnpjs')->onDelete('cascade');

            $table->unique(['id_canal', 'cnpj_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_cnpj');
    }
};
