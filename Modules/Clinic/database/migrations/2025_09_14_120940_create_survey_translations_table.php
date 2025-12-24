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
        Schema::create(config('clinic.table_prefix') . 'survey_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('survey_id')->index('survey_id')->constrained(config('clinic.table_prefix') . 'survey')->cascadeOnDelete();
            $table->string('locale')->index('locale');
            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('clinic.table_prefix') . 'survey_translations');
    }
};
