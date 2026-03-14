<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->decimal('total_produtos', 10, 2)->nullable()->after('valor_total_venda');
            $table->decimal('comissao', 10, 2)->nullable()->after('valor_frete_transportadora');
            $table->decimal('subsidio_pix', 10, 2)->nullable()->after('comissao');
            $table->decimal('base_imposto', 10, 2)->nullable()->after('subsidio_pix');
            $table->decimal('percentual_imposto', 5, 2)->nullable()->after('base_imposto');
            $table->decimal('valor_imposto', 10, 2)->nullable()->after('percentual_imposto');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn([
                'total_produtos', 'comissao', 'subsidio_pix',
                'base_imposto', 'percentual_imposto', 'valor_imposto',
            ]);
        });
    }
};
