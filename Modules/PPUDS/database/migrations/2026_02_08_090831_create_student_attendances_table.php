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
        Schema::create(config('ppuds.table_prefix') . 'student_attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_company_id')->index('student_company_id')->constrained(config('ppuds.table_prefix') . 'students_companies')->cascadeOnDelete();
            $table->date('attendance_date')->index('attendance_date');

            $table->dateTime('check_in')->nullable();
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();

            $table->dateTime('check_out')->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();

            $table->string('status')->default('present');
            $table->text('description')->nullable();
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
        Schema::dropIfExists(config('ppuds.table_prefix') . 'student_attendances');
    }
};
