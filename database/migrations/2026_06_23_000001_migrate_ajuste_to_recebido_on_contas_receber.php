<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Converte registros com status='ajuste' para status='recebido' sem conta_bancaria_id
        // Eles continuam fora do caixa pois o caixa exige conta_bancaria_id via whereHas
        DB::table('contas_receber')
            ->where('status', 'ajuste')
            ->update([
                'status' => 'recebido',
                'conta_bancaria_id' => null,
            ]);
    }

    public function down(): void
    {
        // Irreversível: não é possível distinguir quais eram 'ajuste' após a migração
    }
};
