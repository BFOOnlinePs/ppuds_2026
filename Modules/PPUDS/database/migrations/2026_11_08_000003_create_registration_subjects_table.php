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
        // المواد الجامعية التي درسها الطالب، تُدخل يدوياً لكل تسجيل (مرحلة تدريبية).
        Schema::create(config('ppuds.table_prefix') . 'registration_subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')->constrained(config('ppuds.table_prefix') . 'registrations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'registration_subjects');
    }
};
