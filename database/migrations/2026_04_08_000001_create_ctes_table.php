<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_cte', 20)->nullable();
            $table->string('chave_cte', 50)->nullable();
            $table->string('chave_nfe', 50)->index();
            $table->decimal('valor_frete', 10, 2);
            $table->string('remetente', 200)->nullable();
            $table->string('destinatario', 200)->nullable();
            $table->string('transportadora', 200)->nullable();
            $table->string('arquivo', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctes');
    }
};
