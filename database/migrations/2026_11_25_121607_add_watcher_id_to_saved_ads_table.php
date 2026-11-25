<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_ads', function (Blueprint $table) {
            $table->foreignId('watcher_id')->nullable()->constrained()->after('olx_id');
        });
    }

    public function down(): void
    {
        Schema::table('saved_ads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('watcher_id');
        });
    }
};
