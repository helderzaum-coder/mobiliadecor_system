<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regras_comissao', function (Blueprint $table) {
            $table->decimal('subsidio_pix', 5, 2)->default(0)->after('faixa_valor_max')
                ->comment('Percentual de subsídio pix aplicado sobre o valor do item');
        });
    }

    public function down(): void
    {
        Schema::table('regras_comissao', function (Blueprint $table) {
            $table->dropColumn('subsidio_pix');
        });
    }
};
