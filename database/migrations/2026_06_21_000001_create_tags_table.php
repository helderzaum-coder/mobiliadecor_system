<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('cor')->default('#6b7280');
            $table->timestamps();
        });

        Schema::create('produto_estoque_tag', function (Blueprint $table) {
            $table->foreignId('produto_estoque_id')->constrained('produtos_estoque')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['produto_estoque_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_estoque_tag');
        Schema::dropIfExists('tags');
    }
};
