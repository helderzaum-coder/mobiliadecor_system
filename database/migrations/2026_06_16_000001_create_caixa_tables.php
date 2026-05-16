<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('banco', 100)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('conta', 30)->nullable();
            $table->decimal('saldo_inicial', 12, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('categorias_financeiras', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->enum('tipo', ['entrada', 'saida', 'ambos'])->default('ambos');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('nome');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->unsignedBigInteger('conta_bancaria_id')->nullable()->after('lancamento_manual');
            $table->unsignedBigInteger('categoria_id')->nullable()->after('conta_bancaria_id');

            $table->foreign('conta_bancaria_id')->references('id')->on('contas_bancarias')->nullOnDelete();
            $table->foreign('categoria_id')->references('id')->on('categorias_financeiras')->nullOnDelete();
        });

        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->unsignedBigInteger('conta_bancaria_id')->nullable()->after('lancamento_manual');
            $table->unsignedBigInteger('categoria_id')->nullable()->after('conta_bancaria_id');

            $table->foreign('conta_bancaria_id')->references('id')->on('contas_bancarias')->nullOnDelete();
            $table->foreign('categoria_id')->references('id')->on('categorias_financeiras')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropForeign(['conta_bancaria_id']);
            $table->dropForeign(['categoria_id']);
            $table->dropColumn(['conta_bancaria_id', 'categoria_id']);
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropForeign(['conta_bancaria_id']);
            $table->dropForeign(['categoria_id']);
            $table->dropColumn(['conta_bancaria_id', 'categoria_id']);
        });

        Schema::dropIfExists('categorias_financeiras');
        Schema::dropIfExists('contas_bancarias');
    }
};
