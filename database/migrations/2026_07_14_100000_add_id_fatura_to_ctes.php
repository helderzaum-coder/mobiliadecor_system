<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_fatura')->nullable()->after('tipo');
            $table->index('id_fatura');
        });
    }

    public function down(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->dropIndex(['id_fatura']);
            $table->dropColumn('id_fatura');
        });
    }
};
