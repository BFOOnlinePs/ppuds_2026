<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Clinic\Enums\CustomerProgramStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('clinic.table_prefix') . 'customer_programs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->index('customer_id')->constrained(config('clinic.table_prefix') . 'customer_profiles')->cascadeOnDelete();
            $table->foreignId('program_id')->index('program_id')->constrained(config('clinic.table_prefix') . 'programs')->cascadeOnDelete();
            $table->date('start_date');
            $table->integer('status')->default(CustomerProgramStatus::ACTIVE->value);
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
        Schema::dropIfExists(config('clinic.table_prefix') . 'customer_programs');
    }
};
