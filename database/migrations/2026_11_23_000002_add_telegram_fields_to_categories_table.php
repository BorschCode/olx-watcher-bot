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
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('show_location')->default(true)->after('options');
            $table->boolean('show_rooms')->default(true)->after('show_location');
            $table->boolean('show_square_meters')->default(true)->after('show_rooms');
            $table->boolean('show_sale_date')->default(true)->after('show_square_meters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'show_location',
                'show_rooms',
                'show_square_meters',
                'show_sale_date',
            ]);
        });
    }
};
