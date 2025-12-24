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
        Schema::create(config('clinic.table_prefix') . 'programs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->index('category_id')->constrained(config('clinic.table_prefix') . 'program_categories')->cascadeOnDelete();
            $table->foreignId('instruction_id')->index('instruction_id')->constrained(config('clinic.table_prefix') . 'instructions')->cascadeOnDelete();
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
        Schema::dropIfExists(config('clinic.table_prefix') . 'programs');
    }
};
