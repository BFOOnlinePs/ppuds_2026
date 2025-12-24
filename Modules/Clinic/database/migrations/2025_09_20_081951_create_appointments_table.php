<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Clinic\Enums\AppointmentStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('clinic.table_prefix') . 'appointments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->index('customer_id')->constrained(config('clinic.table_prefix') . 'customer_profiles')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('status')->default(AppointmentStatus::PENDING->value);
            $table->foreignId('room_id')->index('room_id')->constrained(config('clinic.table_prefix') . 'rooms')->cascadeOnDelete();
            $table->foreignId('created_by')->index('created_by')->constrained('users');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('clinic.table_prefix') . 'appointments');
    }
};
