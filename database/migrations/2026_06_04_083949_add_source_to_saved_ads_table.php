<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_ads', function (Blueprint $table) {
            $table->string('source', 20)->default('olx')->after('olx_id');

            $table->dropUnique(['olx_id', 'telegram_chat_id']);
            $table->unique(['olx_id', 'source', 'telegram_chat_id']);
        });
    }

    public function down(): void
    {
        Schema::table('saved_ads', function (Blueprint $table) {
            $table->dropUnique(['olx_id', 'source', 'telegram_chat_id']);
            $table->unique(['olx_id', 'telegram_chat_id']);
            $table->dropColumn('source');
        });
    }
};
