<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajustar canais_venda: remover percentuais fixos, adicionar tipo de nota
        Schema::table('canais_venda', function (Blueprint $table) {
            $table->dropColumn(['percentual_comissao', 'percentual_imposto']);
            $table->enum('tipo_nota', ['cheia', 'produto', 'meia_nota'])
                ->default('cheia')
                ->after('nome_canal')
                ->comment('cheia=total pedido, produto=sem frete, meia_nota=metade do produto');
        });

        // Tabela de regras de comissão por canal
        Schema::create('regras_comissao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_canal');
            $table->string('nome_regra', 191);
            $table->text('descricao')->nullable();
            $table->decimal('percentual', 5, 2);
            $table->decimal('valor_fixo', 10, 2)->default(0);
            $table->decimal('faixa_valor_min', 10, 2)->nullable();
            $table->decimal('faixa_valor_max', 10, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->foreign('id_canal')->references('id_canal')->on('canais_venda');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regras_comissao');

        Schema::table('canais_venda', function (Blueprint $table) {
            $table->dropColumn('tipo_nota');
            $table->decimal('percentual_comissao', 5, 2)->default(0);
            $table->decimal('percentual_imposto', 5, 2)->default(0);
        });
    }
};
