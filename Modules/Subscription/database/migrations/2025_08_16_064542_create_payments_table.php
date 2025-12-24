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
        Schema::create(config('subscription.table_prefix') . 'payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')->constrained(config('subscription.table_prefix') . 'subscriptions');
            $table->decimal('amount', 8, 2)->default(0);
            $table->date('payment_date')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('subscription.table_prefix') . 'payments');
    }
};
