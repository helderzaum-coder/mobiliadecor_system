<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->unsignedBigInteger('bling_id')->nullable()->unique()->after('id_venda');
            $table->string('bling_account', 50)->nullable()->after('bling_id');
            $table->string('cliente_nome', 191)->nullable()->after('data_venda');
            $table->string('cliente_documento', 20)->nullable()->after('cliente_nome');
            $table->decimal('valor_frete_transportadora', 10, 2)->nullable()->after('valor_frete_cliente');
            $table->text('observacoes')->nullable()->after('frete_pago');
            $table->integer('bling_situacao_id')->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn([
                'bling_id', 'bling_account', 'cliente_nome', 'cliente_documento',
                'valor_frete_transportadora', 'observacoes', 'bling_situacao_id',
            ]);
        });
    }
};
