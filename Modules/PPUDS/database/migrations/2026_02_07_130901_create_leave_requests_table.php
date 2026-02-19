<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Enums\LeaveRequestType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'leave_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_company_id')->index()->constrained(config('ppuds.table_prefix') . 'students_companies')->cascadeOnDelete();
            $table->integer('type')->default(LeaveRequestType::SICK->value);
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->text('reason')->nullable();
            $table->string('company_approval')->default(LeaveRequestStatus::APPROVED->value);
            $table->string('university_approval')->default(LeaveRequestStatus::APPROVED->value);
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->index()->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'leave_requests');
    }
};
