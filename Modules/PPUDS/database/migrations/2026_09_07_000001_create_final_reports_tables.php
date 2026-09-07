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
        $prefix = config('ppuds.table_prefix');

        Schema::create($prefix.'final_reports', function (Blueprint $table) use ($prefix) {
            $table->id();

            $table->foreignId('registration_id')
                ->unique()
                ->constrained($prefix.'registrations')
                ->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();

            // نظرة الطالب على دوره خلال فترة التدريب، والملخص النهائي (محرر نصوص)
            $table->longText('role_description')->nullable();
            $table->longText('summary')->nullable();

            $table->unsignedTinyInteger('status')->default(1)->index();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
        });

        Schema::create($prefix.'final_report_tasks', function (Blueprint $table) use ($prefix) {
            $table->id();

            $table->foreignId('final_report_id')
                ->constrained($prefix.'final_reports')
                ->cascadeOnDelete();

            $table->string('task_name');
            $table->text('task_details')->nullable();
            $table->string('work_duration')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        Schema::create($prefix.'final_report_skills', function (Blueprint $table) use ($prefix) {
            $table->id();

            $table->foreignId('final_report_id')
                ->constrained($prefix.'final_reports')
                ->cascadeOnDelete();

            $table->string('skill');
            $table->unsignedTinyInteger('mastery_percentage')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        // أهم المساهمات وصعوبات التدريب: قوائم نصية مرقّمة يفرّق بينها العمود type
        Schema::create($prefix.'final_report_items', function (Blueprint $table) use ($prefix) {
            $table->id();

            $table->foreignId('final_report_id')
                ->constrained($prefix.'final_reports')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('type')->index();
            $table->text('content');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $prefix = config('ppuds.table_prefix');

        Schema::dropIfExists($prefix.'final_report_items');
        Schema::dropIfExists($prefix.'final_report_skills');
        Schema::dropIfExists($prefix.'final_report_tasks');
        Schema::dropIfExists($prefix.'final_reports');
    }
};
