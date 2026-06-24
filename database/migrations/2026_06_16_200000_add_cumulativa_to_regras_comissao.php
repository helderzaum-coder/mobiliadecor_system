<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regras_comissao', function (Blueprint $table) {
            $table->boolean('cumulativa')->default(false)->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('regras_comissao', function (Blueprint $table) {
            $table->dropColumn('cumulativa');
        });
    }
};
