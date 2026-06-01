<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes_recebimento', function (Blueprint $table) {
            $table->id();
            $table->date('data_recebimento');
            $table->string('descricao')->nullable();
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->integer('quantidade_contas')->default(0);
            $table->timestamps();
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->unsignedBigInteger('lote_recebimento_id')->nullable()->after('categoria_id');
            $table->foreign('lote_recebimento_id')->references('id')->on('lotes_recebimento')->nullOnDelete();
        });

        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->unsignedBigInteger('lote_recebimento_id')->nullable()->after('categoria_id');
            $table->foreign('lote_recebimento_id')->references('id')->on('lotes_recebimento')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropForeign(['lote_recebimento_id']);
            $table->dropColumn('lote_recebimento_id');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropForeign(['lote_recebimento_id']);
            $table->dropColumn('lote_recebimento_id');
        });

        Schema::dropIfExists('lotes_recebimento');
    }
};
