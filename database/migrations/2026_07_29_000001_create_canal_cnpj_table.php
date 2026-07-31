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
            $table->unsignedBigInteger('id_canal')->index();
            $table->unsignedBigInteger('cnpj_id')->index();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['id_canal', 'cnpj_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_cnpj');
    }
};
