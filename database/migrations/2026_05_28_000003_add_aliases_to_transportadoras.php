<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            $table->json('aliases')->nullable()->after('nome_transportadora');
        });
    }

    public function down(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};
