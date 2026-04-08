<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->decimal('frete_cotado', 10, 2)->nullable()->after('valor_frete_transportadora');
        });

        // Copiar valor atual da cotação para o novo campo (vendas que ainda não têm frete pago)
        \Illuminate\Support\Facades\DB::statement("
            UPDATE vendas SET frete_cotado = valor_frete_transportadora WHERE frete_pago = 0 AND valor_frete_transportadora > 0
        ");
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('frete_cotado');
        });
    }
};
