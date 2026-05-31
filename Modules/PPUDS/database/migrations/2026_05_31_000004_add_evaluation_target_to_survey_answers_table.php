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
        Schema::table(config('ppuds.table_prefix').'survey_answers', function (Blueprint $table) {
            $table->foreignId('student_company_id')
                ->nullable()
                ->after('submitted_by')
                ->index('survey_ans_stu_company_idx');

            $table->foreignId('evaluated_student_id')
                ->nullable()
                ->after('student_company_id')
                ->index('survey_ans_eval_student_idx');

            $table->index(
                ['survey_id', 'submitted_by', 'student_company_id'],
                'survey_ans_survey_user_stu_company_idx'
            );
        });

        Schema::table(config('ppuds.table_prefix').'survey_answers', function (Blueprint $table) {
            $table->foreign('student_company_id', 'survey_ans_stu_company_fk')
                ->references('id')
                ->on(config('ppuds.table_prefix').'students_companies')
                ->nullOnDelete();

            $table->foreign('evaluated_student_id', 'survey_ans_eval_student_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix').'survey_answers', function (Blueprint $table) {
            $table->dropForeign('survey_ans_stu_company_fk');
            $table->dropForeign('survey_ans_eval_student_fk');
            $table->dropIndex('survey_ans_survey_user_stu_company_idx');
            $table->dropIndex('survey_ans_stu_company_idx');
            $table->dropIndex('survey_ans_eval_student_idx');
            $table->dropColumn(['student_company_id', 'evaluated_student_id']);
        });
    }
};
