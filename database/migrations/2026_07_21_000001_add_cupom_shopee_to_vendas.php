<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->decimal('cupom_shopee', 10, 2)->default(0)->after('subsidio_magalu');
            $table->string('cupom_shopee_descricao', 100)->nullable()->after('cupom_shopee');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['cupom_shopee', 'cupom_shopee_descricao']);
        });
    }
};
