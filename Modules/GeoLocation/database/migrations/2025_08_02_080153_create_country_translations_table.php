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
        Schema::create(config('geolocation.table_prefix') . 'country_translations', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('locale' , 5)->index();
            $table->foreignId('country_id')->index('country_id')->constrained(config('geolocation.table_prefix') . 'countries')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('geolocation.table_prefix') . 'country_translations');
    }
};
