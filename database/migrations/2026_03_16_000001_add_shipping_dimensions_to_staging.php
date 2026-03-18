<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->string('dest_cep', 10)->nullable()->after('ml_shipping_id');
            $table->string('dest_cidade', 100)->nullable()->after('dest_cep');
            $table->string('dest_uf', 2)->nullable()->after('dest_cidade');
            $table->decimal('peso_bruto', 10, 3)->nullable()->after('dest_uf');
            $table->decimal('embalagem_largura', 10, 2)->nullable()->after('peso_bruto');
            $table->decimal('embalagem_altura', 10, 2)->nullable()->after('embalagem_largura');
            $table->decimal('embalagem_comprimento', 10, 2)->nullable()->after('embalagem_altura');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->dropColumn([
                'dest_cep',
                'dest_cidade',
                'dest_uf',
                'peso_bruto',
                'embalagem_largura',
                'embalagem_altura',
                'embalagem_comprimento',
            ]);
        });
    }
};
