<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_itens_ignorados', function (Blueprint $table) {
            $table->id();
            $table->string('item_id', 30)->index();
            $table->string('promotion_id', 50)->index();
            $table->string('account_key', 20);
            $table->timestamps();

            $table->unique(['item_id', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_itens_ignorados');
    }
};
