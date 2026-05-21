<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend url column from varchar(255) to text
        Schema::table('watchers', function (Blueprint $table) {
            $table->text('url')->nullable()->change();
        });

        Schema::table('filter_options', function (Blueprint $table) {
            $table->jsonb('applicable_methods')->nullable()->after('has_range');
        });

        // Location, sort, and currency options are GET URL-param specific;
        // they don't apply to GraphQL where these are derived from category/city selects
        // or hardcoded in buildSearchParameters().
        DB::table('filter_options')
            ->whereIn('group', ['location', 'other', 'currency'])
            ->update(['applicable_methods' => json_encode(['get'])]);
    }

    public function down(): void
    {
        Schema::table('filter_options', function (Blueprint $table) {
            $table->dropColumn('applicable_methods');
        });

        Schema::table('watchers', function (Blueprint $table) {
            $table->string('url')->nullable()->change();
        });
    }
};
