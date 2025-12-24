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
        Schema::create(config('geolocation.table_prefix') . 'district_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('district_id')->index('district_id')->constrained(config('geolocation.table_prefix') . 'districts');
            $table->string('locale', 5)->index();
            $table->string('name');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('geolocation.table_prefix') . 'district_translations');
    }
};
