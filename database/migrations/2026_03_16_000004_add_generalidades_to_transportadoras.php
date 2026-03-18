<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            // Generalidades - taxas padrão da transportadora
            $table->decimal('taxa_despacho', 10, 2)->default(0)->after('ativo');
            $table->decimal('pedagio_fracao_kg', 10, 2)->default(100)->after('taxa_despacho'); // a cada X kg
            $table->decimal('pedagio_valor', 10, 2)->default(0)->after('pedagio_fracao_kg');
            $table->decimal('adv_percentual', 8, 4)->default(0)->after('pedagio_valor'); // % sobre NF
            $table->decimal('gris_percentual', 8, 4)->default(0)->after('adv_percentual'); // % sobre NF
            $table->decimal('gris_minimo', 10, 2)->default(0)->after('gris_percentual'); // mínimo GRIS
            $table->decimal('trt_valor', 10, 2)->default(0)->after('gris_minimo'); // Taxa Restrição Trânsito
            $table->decimal('tas_valor', 10, 2)->default(0)->after('trt_valor'); // TAS
            $table->decimal('tda_valor', 10, 2)->default(0)->after('tas_valor'); // Taxa Difícil Acesso (padrão)
            $table->decimal('reentrega_percentual', 8, 4)->default(0)->after('tda_valor'); // % sobre frete
            $table->decimal('devolucao_percentual', 8, 4)->default(0)->after('reentrega_percentual'); // % sobre frete
        });
    }

    public function down(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            $table->dropColumn([
                'taxa_despacho',
                'pedagio_fracao_kg',
                'pedagio_valor',
                'adv_percentual',
                'gris_percentual',
                'gris_minimo',
                'trt_valor',
                'tas_valor',
                'tda_valor',
                'reentrega_percentual',
                'devolucao_percentual',
            ]);
        });
    }
};
