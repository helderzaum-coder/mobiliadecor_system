<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->decimal('ml_sale_fee', 10, 2)->default(0)->after('ml_valor_rebate');
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->decimal('ml_sale_fee', 10, 2)->default(0)->after('ml_valor_rebate');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->dropColumn('ml_sale_fee');
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('ml_sale_fee');
        });
    }
};
