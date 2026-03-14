<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->string('nfe_numero', 20)->nullable()->after('nota_fiscal');
            $table->string('nfe_chave_acesso', 50)->nullable()->after('nfe_numero');
            $table->decimal('nfe_valor', 10, 2)->nullable()->after('nfe_chave_acesso');
            $table->text('nfe_xml_url')->nullable()->after('nfe_valor');
            $table->text('nfe_pdf_url')->nullable()->after('nfe_xml_url');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bling_staging', function (Blueprint $table) {
            $table->dropColumn([
                'nfe_numero', 'nfe_chave_acesso', 'nfe_valor',
                'nfe_xml_url', 'nfe_pdf_url',
            ]);
        });
    }
};
