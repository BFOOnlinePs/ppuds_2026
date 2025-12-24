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
        Schema::create(config('clinic.table_prefix') . 'program_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_id')->index('program_id')->constrained(config('clinic.table_prefix') . 'programs')->cascadeOnDelete();
            $table->foreignId('program_customer_id')->nullable()->index('program_customer_id')->constrained(config('clinic.table_prefix') . 'customer_programs')->cascadeOnDelete();
            $table->unsignedInteger('day_number');
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
        Schema::dropIfExists(config('clinic.table_prefix') . 'program_days');
    }
};
