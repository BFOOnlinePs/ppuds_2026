<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('ppuds.table_prefix') . 'survey_question_option_translations';
        $relatedTable = config('ppuds.table_prefix') . 'survey_question_options';

        Schema::create($tableName, function (Blueprint $table) use ($relatedTable) {
            $table->id();
            
            $table->unsignedBigInteger('survey_question_option_id');
            $table->foreign('survey_question_option_id', 'sqo_trans_fk')
                ->references('id')
                ->on($relatedTable)
                ->cascadeOnDelete();


            $table->string('locale')->index();
            $table->text('text');

            $table->timestamps();
            
            $table->unique(['survey_question_option_id', 'locale'], 'sqo_trans_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'survey_question_option_translations');
    }
};