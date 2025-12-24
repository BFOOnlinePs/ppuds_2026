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
        Schema::create(config('coupon.table_prefix') . 'coupons', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique()->index();
            $table->integer('type');
            $table->decimal('value', 10, 2);
            $table->integer('max_uses')->nullable();
            $table->integer('current_uses')->default(0);
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('coupon.table_prefix') . 'coupons');
    }
};
