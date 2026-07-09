<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->string('dest_documento', 20)->nullable()->after('destinatario');
            $table->string('rem_documento', 20)->nullable()->after('remetente');
            $table->string('numero_nfe', 20)->nullable()->after('chave_nfe');
        });
    }

    public function down(): void
    {
        Schema::table('ctes', function (Blueprint $table) {
            $table->dropColumn(['dest_documento', 'rem_documento', 'numero_nfe']);
        });
    }
};
