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
        Schema::create('filter_option_watcher', function (Blueprint $table) {
            $table->foreignId('watcher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('filter_option_id')->constrained()->cascadeOnDelete();
            $table->string('value_from')->nullable();
            $table->string('value_to')->nullable();
            $table->primary(['watcher_id', 'filter_option_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_option_watcher');
    }
};
