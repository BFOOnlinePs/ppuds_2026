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
        Schema::create(config('clinic.table_prefix') . 'food_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('food_category_id')->index('food_category_id')->constrained(config('clinic.table_prefix') . 'food_category');
            $table->foreignId('created_by')->index('created_by')->constrained('users');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('clinic.table_prefix') . 'food_items');
    }
};
