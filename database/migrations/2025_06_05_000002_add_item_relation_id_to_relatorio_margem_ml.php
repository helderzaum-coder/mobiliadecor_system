<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relatorio_margem_ml', function (Blueprint $table) {
            $table->string('item_relation_id', 50)->nullable()->after('is_catalog_listing');
        });
    }

    public function down(): void
    {
        Schema::table('relatorio_margem_ml', function (Blueprint $table) {
            $table->dropColumn('item_relation_id');
        });
    }
};
