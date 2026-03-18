<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campos ML no staging
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->string('ml_tipo_anuncio')->nullable()->after('canal');
            $table->string('ml_tipo_frete')->nullable()->after('ml_tipo_anuncio');
            $table->boolean('ml_tem_rebate')->default(false)->after('ml_tipo_frete');
            $table->decimal('ml_valor_rebate', 10, 2)->default(0)->after('ml_tem_rebate');
            $table->string('ml_order_id')->nullable()->after('ml_valor_rebate');
            $table->string('ml_shipping_id')->nullable()->after('ml_order_id');
        });

        // Campos ML nas vendas
        Schema::table('vendas', function (Blueprint $table) {
            $table->string('ml_tipo_anuncio')->nullable()->after('observacoes');
            $table->string('ml_tipo_frete')->nullable()->after('ml_tipo_anuncio');
            $table->boolean('ml_tem_rebate')->default(false)->after('ml_tipo_frete');
            $table->decimal('ml_valor_rebate', 10, 2)->default(0)->after('ml_tem_rebate');
            $table->string('ml_order_id')->nullable()->after('ml_valor_rebate');
            $table->string('ml_shipping_id')->nullable()->after('ml_order_id');
        });
    }

    public function down(): void
    {
        $campos = ['ml_tipo_anuncio', 'ml_tipo_frete', 'ml_tem_rebate', 'ml_valor_rebate', 'ml_order_id', 'ml_shipping_id'];

        Schema::table('pedidos_bling_staging', function (Blueprint $table) use ($campos) {
            $table->dropColumn($campos);
        });

        Schema::table('vendas', function (Blueprint $table) use ($campos) {
            $table->dropColumn($campos);
        });
    }
};
