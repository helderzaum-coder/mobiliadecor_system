<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bling_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('account_key', 50); // 'primary' ou 'secondary'
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique('account_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bling_tokens');
    }
};
