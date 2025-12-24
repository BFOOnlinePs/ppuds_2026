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
        Schema::create(config('clinic.table_prefix') . 'responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('survey_id')->index('survey_id')->constrained(config('clinic.table_prefix') . 'survey')->cascadeOnDelete();
            $table->foreignId('customer_id')->index('customer_id')->constrained(config('clinic.table_prefix') . 'customer_profiles')->cascadeOnDelete();
            $table->foreignId('created_by')->index('user_id')->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('clinic.table_prefix') . 'responses');
    }
};
