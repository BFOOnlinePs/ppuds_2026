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
        Schema::create(config('clinic.table_prefix') . 'serving_sizes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('food_item_id')->index('food_item_id')->constrained(config('clinic.table_prefix') . 'food_items');
            $table->decimal('gram');
            $table->decimal('calories')->nullable();
            $table->decimal('protein')->nullable();
            $table->decimal('carbohydrate')->nullable();
            $table->decimal('fat')->nullable();
            $table->decimal('fiber')->nullable();
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
        Schema::dropIfExists(config('clinic.table_prefix') . 'serving_sizes');
    }
};
