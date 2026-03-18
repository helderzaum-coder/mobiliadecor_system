<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercadolivre_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('account_key')->unique();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercadolivre_tokens');
    }
};
