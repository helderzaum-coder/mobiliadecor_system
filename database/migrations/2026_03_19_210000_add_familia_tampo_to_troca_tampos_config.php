<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('troca_tampos_config', function (Blueprint $table) {
            $table->string('familia_tampo', 50)->after('cor_tampo')->default('')
                ->comment('Grupos que compartilham tampos. Ex: alana, elisa_jade, evelyn_amanda');
        });
    }

    public function down(): void
    {
        Schema::table('troca_tampos_config', function (Blueprint $table) {
            $table->dropColumn('familia_tampo');
        });
    }
};
