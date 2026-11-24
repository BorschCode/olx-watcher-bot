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
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_ad_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('old_price')->nullable();
            $table->unsignedInteger('new_price')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index('saved_ad_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
