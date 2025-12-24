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
        Schema::create(config('clinic.table_prefix') . 'program_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_id')->index('program_id')->constrained(config('clinic.table_prefix') . 'programs')->cascadeOnDelete();
            $table->string('locale')->index('locale');
            $table->string('name')->unique();
            $table->string('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('clinic.table_prefix') . 'program_translations');
    }
};
