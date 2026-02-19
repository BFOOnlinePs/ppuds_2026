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
        Schema::create(config('ppuds.table_prefix') . 'survey_answers', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('survey_id')->constrained(config('ppuds.table_prefix') . 'surveys')->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained(config('ppuds.table_prefix') . 'survey_questions')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();

            $table->text('text_answer')->nullable();

            $table->foreignId('selected_option_id')->nullable()->constrained(config('ppuds.table_prefix') . 'survey_question_options')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'survey_answers');
    }
};
