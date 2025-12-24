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
        Schema::create(config('clinic.table_prefix') . 'answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('response_id')->index('response_id')->constrained(config('clinic.table_prefix') . 'responses')->cascadeOnDelete();
            $table->foreignId('question_id')->index('question_id')->constrained(config('clinic.table_prefix') . 'questions')->cascadeOnDelete();
            $table->text('answer_text');
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
        Schema::dropIfExists(config('clinic.table_prefix') . 'answers');
    }
};
