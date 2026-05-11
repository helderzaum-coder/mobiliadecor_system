<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('troca_tampos_config', function (Blueprint $table) {
            $table->boolean('equalizacao_ativa')->default(true)->after('familia_tampo');
        });
    }

    public function down(): void
    {
        Schema::table('troca_tampos_config', function (Blueprint $table) {
            $table->dropColumn('equalizacao_ativa');
        });
    }
};
