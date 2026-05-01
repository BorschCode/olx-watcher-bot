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
        Schema::table('watchers', function (Blueprint $table) {
            $table->string('method')->default('get')->after('telegram_chat_id');
            $table->json('request_body')->nullable()->after('url');
            $table->string('url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('watchers', function (Blueprint $table) {
            $table->dropColumn(['method', 'request_body']);
            $table->string('url')->nullable(false)->change();
        });
    }
};
