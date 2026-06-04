<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchers', function (Blueprint $table) {
            $table->string('source', 20)->default('olx')->after('telegram_chat_id');
            $table->string('method', 20)->nullable()->change();
        });

        // Migrate any existing auto_ria method rows to the new source column
        DB::table('watchers')
            ->where('method', 'auto_ria')
            ->update(['source' => 'auto_ria', 'method' => null]);
    }

    public function down(): void
    {
        // Restore method = 'auto_ria' from source before dropping the column
        DB::table('watchers')
            ->where('source', 'auto_ria')
            ->update(['method' => 'auto_ria']);

        Schema::table('watchers', function (Blueprint $table) {
            $table->dropColumn('source');
            $table->string('method', 20)->nullable(false)->change();
        });
    }
};
