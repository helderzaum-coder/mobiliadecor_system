<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('troca_tampos_config', function (Blueprint $table) {
            $table->id();
            $table->string('grupo', 50)->comment('Ex: Alana, Evelyn, Fran');
            $table->string('cor', 50)->comment('Ex: Branco, Savana/Preto, Savana/Off-White');
            $table->string('tipo_tampo', 30)->comment('4bocas, 5bocas, liso');
            $table->string('sku_produto')->comment('SKU do produto montado no Bling');
            $table->string('sku_tampo')->comment('SKU do tampo avulso no Bling');
            $table->string('nome_produto')->comment('Nome legível do produto montado');
            $table->string('nome_tampo')->comment('Nome legível do tampo');
            $table->string('cor_tampo', 50)->comment('Cor do tampo (ex: branco, savana) - para compatibilidade');
            $table->timestamps();

            $table->unique(['grupo', 'cor', 'tipo_tampo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('troca_tampos_config');
    }
};
