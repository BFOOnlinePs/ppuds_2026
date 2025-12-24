<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\PPUDS\Enums\CvStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'student_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->index('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('dob')->nullable();
            $table->string('gender')->nullable();
            $table->integer('cv_status')->default(CvStatus::PENDING->value)->nullable();
            $table->string('tawjihi_gpa')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'student_profiles');
    }
};
