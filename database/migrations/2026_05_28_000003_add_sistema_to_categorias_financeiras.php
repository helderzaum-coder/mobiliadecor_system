<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias_financeiras', function (Blueprint $table) {
            $table->boolean('sistema')->default(false)->after('ativo');
        });

        DB::table('categorias_financeiras')->updateOrInsert(
            ['nome' => 'Transferência'],
            ['tipo' => 'ambos', 'ativo' => true, 'sistema' => true]
        );
    }

    public function down(): void
    {
        Schema::table('categorias_financeiras', function (Blueprint $table) {
            $table->dropColumn('sistema');
        });
    }
};
