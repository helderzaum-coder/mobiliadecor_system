<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->boolean('dre_lancado')->default(false)->after('cancelada');
            $table->timestamp('dre_lancado_em')->nullable()->after('dre_lancado');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['dre_lancado', 'dre_lancado_em']);
        });
    }
};
