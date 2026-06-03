<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->uuid('transferencia_id')->nullable()->after('grupo_recorrencia');
            $table->index('transferencia_id');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->uuid('transferencia_id')->nullable()->after('lote_recebimento_id');
            $table->index('transferencia_id');
        });
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropIndex(['transferencia_id']);
            $table->dropColumn('transferencia_id');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropIndex(['transferencia_id']);
            $table->dropColumn('transferencia_id');
        });
    }
};
