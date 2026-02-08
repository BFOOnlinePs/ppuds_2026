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
        Schema::create(config('ppuds.table_prefix') . 'student_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_attendance_id')
                ->constrained(config('ppuds.table_prefix') . 'student_attendances')
                ->cascadeOnDelete();

            $table->longText('report_text');

            $table->text('company_feedback')->nullable();
            $table->text('academic_feedback')->nullable();

            $table->decimal('submit_latitude', 10, 8)->nullable();
            $table->decimal('submit_longitude', 11, 8)->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'student_reports');
    }
};
