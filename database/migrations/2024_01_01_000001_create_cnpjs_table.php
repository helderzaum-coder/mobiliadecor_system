<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnpjs', function (Blueprint $table) {
            $table->id('id_cnpj');
            $table->string('numero_cnpj', 18);
            $table->string('razao_social', 100);
            $table->string('regime_tributario', 50);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnpjs');
    }
};
