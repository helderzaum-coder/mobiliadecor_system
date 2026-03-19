<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            $table->boolean('cobertura_completa')->default(false)->after('aplica_icms')
                ->comment('Se true, tabela de frete cobre todos os destinos atendidos. Não mostra "consultar" quando sem faixa.');
        });
    }

    public function down(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            $table->dropColumn('cobertura_completa');
        });
    }
};
