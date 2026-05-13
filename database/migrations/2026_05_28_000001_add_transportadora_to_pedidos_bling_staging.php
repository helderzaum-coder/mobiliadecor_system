<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->string('transportadora', 150)->nullable()->after('dest_uf');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->dropColumn('transportadora');
        });
    }
};
