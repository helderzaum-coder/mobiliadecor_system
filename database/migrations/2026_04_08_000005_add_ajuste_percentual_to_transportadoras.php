<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            $table->decimal('ajuste_percentual', 5, 2)->default(0)->after('tda_valor');
        });
    }

    public function down(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            $table->dropColumn('ajuste_percentual');
        });
    }
};
