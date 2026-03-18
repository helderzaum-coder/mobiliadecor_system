<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->decimal('ml_frete_custo', 10, 2)->default(0)->after('ml_sale_fee');
            $table->decimal('ml_frete_receita', 10, 2)->default(0)->after('ml_frete_custo');
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->decimal('ml_frete_custo', 10, 2)->default(0)->after('ml_sale_fee');
            $table->decimal('ml_frete_receita', 10, 2)->default(0)->after('ml_frete_custo');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->dropColumn(['ml_frete_custo', 'ml_frete_receita']);
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['ml_frete_custo', 'ml_frete_receita']);
        });
    }
};
