<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impostos_mensais', function (Blueprint $table) {
            $table->dropColumn('data_atualizacao');
        });
    }

    public function down(): void
    {
        Schema::table('impostos_mensais', function (Blueprint $table) {
            $table->date('data_atualizacao')->nullable();
        });
    }
};
