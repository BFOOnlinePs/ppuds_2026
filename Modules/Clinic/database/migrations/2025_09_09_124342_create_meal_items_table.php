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
        Schema::create(config('clinic.table_prefix') . 'meal_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('day_meal_id')->index('day_meal_id')->constrained(config('clinic.table_prefix') . 'day_meals')->cascadeOnDelete();
            $table->foreignId('food_item_id')->index('food_item_id')->constrained(config('clinic.table_prefix') . 'food_items')->cascadeOnDelete();
            $table->foreignId('serving_size_id')->index('serving_size_id')->constrained(config('clinic.table_prefix') . 'serving_sizes')->cascadeOnDelete();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->foreignId('created_by')->index('created_by')->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('clinic.table_prefix') . 'meal_items');
    }
};
