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
        Schema::create(config('content.table_prefix') . 'faq_category_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('faq_category_id')->index('faq_category_id')->constrained(config('content.table_prefix') . 'faq_categories')->cascadeOnDelete();

            $table->string('locale')->index('locale');
            $table->string('name');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('content.table_prefix') . 'faq_category_translations');
    }
};
