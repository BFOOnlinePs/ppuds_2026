<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\PPUDS\Enums\TrainingStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'students_companies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')
                ->index()
                ->constrained(config('ppuds.table_prefix') . 'registrations')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->index()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->index()
                ->constrained(config('ppuds.table_prefix') . 'companies');
            $table->foreignId('branch_id')->nullable()->index()
                ->constrained(config('branch.table_prefix') . 'branches');
            $table->foreignId('department_id')
                ->nullable()
                ->constrained(config('ppuds.table_prefix') . 'company_departments') // لاحظ تعديل الاسم هنا
                ->nullOnDelete();
            $table->integer('status')->default(TrainingStatus::AVAILABLE->value)->index();
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
        Schema::dropIfExists(config('ppuds.table_prefix') . 'students_companies');
    }
};
