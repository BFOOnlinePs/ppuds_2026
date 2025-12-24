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
        Schema::create(config('clinic.table_prefix') . 'day_meals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_day_id')->index('program_day_id')->constrained(config('clinic.table_prefix') . 'program_days')->cascadeOnDelete();
            $table->foreignId('type_of_meal_id')->index('type_of_meal_id')->constrained(config('clinic.table_prefix') . 'types_of_meals')->cascadeOnDelete();
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
        Schema::dropIfExists(config('clinic.table_prefix') . 'day_meals');
    }
};
