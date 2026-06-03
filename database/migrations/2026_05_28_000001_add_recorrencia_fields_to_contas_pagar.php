<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->string('descricao', 150)->nullable()->after('id_fatura');
            $table->date('data_lancamento')->nullable()->after('data_pagamento');
            $table->boolean('recorrente')->default(false)->after('total_parcelas');
            $table->string('intervalo_recorrencia', 20)->nullable()->after('recorrente');
            $table->date('data_fim_recorrencia')->nullable()->after('intervalo_recorrencia');
            $table->decimal('juros_atraso', 5, 2)->nullable()->after('data_fim_recorrencia');
            $table->string('tipo_juros', 10)->nullable()->after('juros_atraso');
            $table->uuid('grupo_recorrencia')->nullable()->after('tipo_juros');

            $table->index('grupo_recorrencia');
        });
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropIndex(['grupo_recorrencia']);
            $table->dropColumn([
                'descricao',
                'data_lancamento',
                'recorrente',
                'intervalo_recorrencia',
                'data_fim_recorrencia',
                'juros_atraso',
                'tipo_juros',
                'grupo_recorrencia',
            ]);
        });
    }
};
