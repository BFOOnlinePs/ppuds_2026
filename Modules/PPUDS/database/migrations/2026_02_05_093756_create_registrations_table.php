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
        Schema::create(config('ppuds.table_prefix') . 'registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->index('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->index('course_id')->constrained(config('ppuds.table_prefix') . 'courses')->cascadeOnDelete();
            $table->float('grade')->nullable();
            $table->integer('semester');
            $table->integer('year');
            $table->foreignId('supervisor_id')->index('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->float('university_score')->nullable();
            $table->float('company_score')->nullable();
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
        Schema::dropIfExists(config('ppuds.table_prefix') . 'registrations');
    }
};
