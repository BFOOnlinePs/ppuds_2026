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
        Schema::table(config('ppuds.table_prefix') . 'student_profiles', function (Blueprint $table) {
            $table->string('student_number')->unique()->after('id');

            $table->year('enrollment_year')->nullable();
            $table->unsignedTinyInteger('semester_level')->nullable();

            $table->foreignId('major_id')->nullable()->constrained(config('ppuds.table_prefix') . 'majors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'student_profiles', function (Blueprint $table) {
            $table->dropColumn('student_number');
            $table->dropColumn('enrollment_year');
            $table->dropColumn('semester_level');
            $table->dropColumn('major_id');
        });
    }
};
