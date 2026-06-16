<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->decimal('comissao', 10, 2)->nullable()->after('custo_frete');
            $table->decimal('cupom_shopee', 10, 2)->nullable()->after('subsidio_pix');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->dropColumn(['comissao', 'cupom_shopee']);
        });
    }
};
