<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relatorio_margem_ml', function (Blueprint $table) {
            $table->string('user_product_id', 50)->nullable()->after('catalog_product_id');
            $table->string('family_id', 50)->nullable()->after('user_product_id');
            $table->string('family_name')->nullable()->after('family_id');
            $table->string('cor', 100)->nullable()->after('family_name');
            $table->string('status_ml', 20)->nullable()->after('estoque')->comment('active, paused, closed');

            $table->index('family_id');
            $table->index('user_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('relatorio_margem_ml', function (Blueprint $table) {
            $table->dropColumn(['user_product_id', 'family_id', 'family_name', 'cor', 'status_ml']);
        });
    }
};
