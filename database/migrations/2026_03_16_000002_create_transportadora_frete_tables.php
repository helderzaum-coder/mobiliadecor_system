<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UFs atendidas pela transportadora
        Schema::create('transportadora_ufs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_transportadora');
            $table->string('uf', 2);
            $table->timestamps();

            $table->foreign('id_transportadora')
                ->references('id_transportadora')
                ->on('transportadoras')
                ->onDelete('cascade');

            $table->unique(['id_transportadora', 'uf']);
        });

        // Tabela de frete (faixas de peso com preço por kg ou fixo)
        Schema::create('transportadora_tabela_frete', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_transportadora');
            $table->string('uf', 2)->nullable(); // null = todas UFs atendidas
            $table->decimal('peso_min', 10, 3); // kg
            $table->decimal('peso_max', 10, 3); // kg
            $table->decimal('valor_kg', 10, 2)->nullable(); // preço por kg
            $table->decimal('valor_fixo', 10, 2)->nullable(); // ou valor fixo na faixa
            $table->decimal('frete_minimo', 10, 2)->default(0); // frete mínimo
            $table->decimal('gris', 8, 4)->default(0); // % GRIS
            $table->decimal('advalorem', 8, 4)->default(0); // % Ad Valorem
            $table->decimal('pedagio', 10, 2)->default(0); // valor pedágio
            $table->timestamps();

            $table->foreign('id_transportadora')
                ->references('id_transportadora')
                ->on('transportadoras')
                ->onDelete('cascade');
        });

        // Taxas especiais por cidade/CEP (TDA, TRT, TAR, etc)
        Schema::create('transportadora_taxas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_transportadora');
            $table->string('tipo_taxa', 20); // TDA, TRT, TAR, OUTROS
            $table->string('uf', 2)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->string('cep_inicio', 8)->nullable(); // faixa de CEP
            $table->string('cep_fim', 8)->nullable();
            $table->decimal('valor_fixo', 10, 2)->nullable(); // valor fixo
            $table->decimal('percentual', 8, 4)->nullable(); // ou percentual sobre frete
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->foreign('id_transportadora')
                ->references('id_transportadora')
                ->on('transportadoras')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportadora_taxas');
        Schema::dropIfExists('transportadora_tabela_frete');
        Schema::dropIfExists('transportadora_ufs');
    }
};
