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
        Schema::table(config('items.table_prefix') . 'orders', function (Blueprint $table) {
            $table->foreignId('city_id')
                ->nullable()
                ->after('delivery_address')
                ->constrained(config('geolocation.table_prefix') . 'cities')
                ->onDelete('set null');

            $table->foreignId('district_id')
                ->nullable()
                ->after('city_id')
                ->constrained(config('geolocation.table_prefix') . 'districts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('items.table_prefix') . 'orders', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropForeign(['city_id']);

            $table->dropColumn(['city_id', 'district_id']);
        });
    }
};
