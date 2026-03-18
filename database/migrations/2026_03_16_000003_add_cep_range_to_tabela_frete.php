<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportadora_tabela_frete', function (Blueprint $table) {
            $table->string('cep_inicio', 8)->nullable()->after('uf');
            $table->string('cep_fim', 8)->nullable()->after('cep_inicio');
            $table->string('regiao', 50)->nullable()->after('cep_fim'); // Capital, Interior, Litoral, etc
        });
    }

    public function down(): void
    {
        Schema::table('transportadora_tabela_frete', function (Blueprint $table) {
            $table->dropColumn(['cep_inicio', 'cep_fim', 'regiao']);
        });
    }
};
