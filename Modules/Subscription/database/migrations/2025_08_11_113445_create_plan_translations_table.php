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
        Schema::create(config('subscription.table_prefix') . 'plan_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')->index('plan_id')->constrained(config('subscription.table_prefix') . 'plans');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('locale');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('subscription.table_prefix') . 'plan_translations');
    }
};
