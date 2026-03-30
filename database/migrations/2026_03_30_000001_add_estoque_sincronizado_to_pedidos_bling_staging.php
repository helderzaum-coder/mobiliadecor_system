<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->boolean('estoque_sincronizado')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->dropColumn('estoque_sincronizado');
        });
    }
};
