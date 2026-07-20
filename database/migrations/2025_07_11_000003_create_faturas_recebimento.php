<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas_recebimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canal_id')->nullable()->constrained('canais_venda', 'id_canal')->nullOnDelete();
            $table->string('descricao')->nullable();
            $table->date('data_prevista');
            $table->enum('status', ['aberta', 'confirmada', 'cancelada'])->default('aberta');
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->unsignedBigInteger('conta_bancaria_id')->nullable();
            $table->unsignedBigInteger('lote_recebimento_id')->nullable();
            $table->json('descontos')->nullable();
            $table->json('entradas_avulsas')->nullable();
            $table->timestamps();
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->unsignedBigInteger('fatura_recebimento_id')->nullable()->after('lote_recebimento_id');
        });
    }

    public function down(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropColumn('fatura_recebimento_id');
        });
        Schema::dropIfExists('faturas_recebimento');
    }
};
