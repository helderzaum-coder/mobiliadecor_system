<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unique na tabela de frete: mesma transportadora + uf + cep + faixa de peso
        Schema::table('transportadora_tabela_frete', function (Blueprint $table) {
            $table->unique(
                ['id_transportadora', 'uf', 'cep_inicio', 'cep_fim', 'peso_min', 'peso_max'],
                'uq_frete_faixa'
            );
        });

        // Unique nas taxas: mesma transportadora + tipo + uf + cidade + cep
        Schema::table('transportadora_taxas', function (Blueprint $table) {
            $table->unique(
                ['id_transportadora', 'tipo_taxa', 'uf', 'cidade', 'cep_inicio', 'cep_fim'],
                'uq_taxa_faixa'
            );
        });
    }

    public function down(): void
    {
        Schema::table('transportadora_tabela_frete', function (Blueprint $table) {
            $table->dropUnique('uq_frete_faixa');
        });
        Schema::table('transportadora_taxas', function (Blueprint $table) {
            $table->dropUnique('uq_taxa_faixa');
        });
    }
};
