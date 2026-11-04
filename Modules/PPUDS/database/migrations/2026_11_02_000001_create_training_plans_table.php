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
        Schema::create(config('ppuds.table_prefix') . 'training_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('major_id')->constrained(config('ppuds.table_prefix') . 'majors')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained(config('ppuds.table_prefix') . 'courses')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // ليس unique لأن الخطة المحذوفة حذفاً ناعماً يجب ألا تمنع إدخالها من جديد،
            // ومنع التكرار يتم في نموذج الإدخال.
            $table->index(['major_id', 'course_id']);
        });

        Schema::create(config('ppuds.table_prefix') . 'training_plan_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_plan_id')->constrained(config('ppuds.table_prefix') . 'training_plans')->cascadeOnDelete();
            $table->string('locale')->index();
            $table->json('objectives');
            $table->json('tasks');
            $table->json('outcomes');

            $table->timestamps();

            $table->unique(['training_plan_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'training_plan_translations');
        Schema::dropIfExists(config('ppuds.table_prefix') . 'training_plans');
    }
};
