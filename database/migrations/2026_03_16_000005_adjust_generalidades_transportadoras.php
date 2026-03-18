<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            $table->decimal('adv_minimo', 10, 2)->default(0)->after('adv_percentual');
            $table->dropColumn(['reentrega_percentual', 'devolucao_percentual']);
        });
    }

    public function down(): void
    {
        Schema::table('transportadoras', function (Blueprint $table) {
            $table->dropColumn('adv_minimo');
            $table->decimal('reentrega_percentual', 8, 4)->default(0);
            $table->decimal('devolucao_percentual', 8, 4)->default(0);
        });
    }
};
