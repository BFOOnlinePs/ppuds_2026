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
        Schema::create(config('subscription.table_prefix') . 'subscription_freezes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')->constrained(config('subscription.table_prefix') . 'subscriptions')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('reason')->nullable();
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
        Schema::dropIfExists(config('subscription.table_prefix') . 'subscription_freezes');
    }
};
