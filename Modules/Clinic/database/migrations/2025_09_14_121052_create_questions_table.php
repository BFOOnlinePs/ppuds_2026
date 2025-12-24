<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Clinic\Enums\QuestionType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('clinic.table_prefix') . 'questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('survey_id')->index('survey_id')->constrained(config('clinic.table_prefix') . 'survey')->cascadeOnDelete();
            $table->integer('type')->default(QuestionType::TEXTAREA->value);
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
        Schema::dropIfExists(config('clinic.table_prefix') . 'questions');
    }
};
