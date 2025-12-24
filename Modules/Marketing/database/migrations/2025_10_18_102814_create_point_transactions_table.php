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
        Schema::create(config('marketing.table_prefix') . 'point_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->index('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('points',8,2);
            $table->text('description')->nullable();
            $table->morphs('pointable');
            $table->foreignId('created_by')->index('created_by')->nullable()->constrained('users')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('marketing.table_prefix') . 'point_transactions');
    }
};
