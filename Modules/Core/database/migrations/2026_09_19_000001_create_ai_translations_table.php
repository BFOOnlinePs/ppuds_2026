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
        // One row per translated value the AI wrote. A value with no row here
        // was typed by a person, and auto translation never overwrites it.
        Schema::create('ai_translations', function (Blueprint $table) {
            $table->id();

            $table->string('translatable_type');
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 10);
            $table->string('attribute', 100);

            // The text it was translated from, so an edit to it is noticed.
            $table->string('source_locale', 10);
            $table->char('source_hash', 40);

            // What the AI wrote. Once the stored value stops matching, a
            // person has edited it and it is theirs from then on.
            $table->char('value_hash', 40);

            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale', 'attribute'], 'ai_translations_slot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_translations');
    }
};
