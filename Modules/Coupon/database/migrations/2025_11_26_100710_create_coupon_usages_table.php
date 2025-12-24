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
        Schema::create(config('coupon.table_prefix') . 'coupon_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_id')->index('coupon_id')->constrained(config('coupon.table_prefix') . 'coupons')->restrictOnDelete();
            $table->foreignId('user_id')->index('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->index('order_id')->constrained(config('items.table_prefix') . 'orders')->cascadeOnDelete();
            $table->decimal('discount_amount', 10, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('coupon.table_prefix') . 'coupon_usages');
    }
};
