<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saved_ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('olx_id');
            $table->string('telegram_chat_id');
            $table->string('title');
            $table->string('url');
            $table->unsignedInteger('price')->nullable();
            $table->jsonb('images')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['olx_id', 'telegram_chat_id']);
            $table->index('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_ads');
    }
};
