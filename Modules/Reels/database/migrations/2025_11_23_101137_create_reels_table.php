<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Reels\Enums\ReelStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('reels.table_prefix') . 'reels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->index('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('status')->index()->default(ReelStatus::PENDING->value);
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('reels.table_prefix') . 'reels');
    }
};
