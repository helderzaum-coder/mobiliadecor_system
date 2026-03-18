<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportadora_tabela_frete', function (Blueprint $table) {
            $table->decimal('despacho', 10, 2)->nullable()->after('frete_minimo');
            $table->decimal('pedagio_valor', 10, 2)->nullable()->after('despacho');
            $table->decimal('pedagio_fracao_kg', 10, 2)->nullable()->after('pedagio_valor');
            $table->decimal('adv_percentual', 8, 4)->nullable()->after('pedagio_fracao_kg');
            $table->decimal('adv_minimo', 10, 2)->nullable()->after('adv_percentual');
            $table->decimal('gris_percentual', 8, 4)->nullable()->after('adv_minimo');
            $table->decimal('gris_minimo', 10, 2)->nullable()->after('gris_percentual');
        });
    }

    public function down(): void
    {
        Schema::table('transportadora_tabela_frete', function (Blueprint $table) {
            $table->dropColumn([
                'despacho', 'pedagio_valor', 'pedagio_fracao_kg',
                'adv_percentual', 'adv_minimo', 'gris_percentual', 'gris_minimo',
            ]);
        });
    }
};
