<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->boolean('utilizado')->default(false)->after('arquivo');
            $table->unsignedBigInteger('venda_id')->nullable()->after('utilizado');
        });
    }

    public function down(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->dropColumn(['utilizado', 'venda_id']);
        });
    }
};
