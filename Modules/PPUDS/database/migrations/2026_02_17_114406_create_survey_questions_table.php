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
        Schema::create(config('ppuds.table_prefix') . 'survey_questions', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('survey_id')->constrained(config('ppuds.table_prefix') . 'surveys')->cascadeOnDelete();
            $table->integer('type')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'survey_questions');
    }
};
