<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Coupon\Enums\ExclusionType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('coupon.table_prefix') . 'couponables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_id')->constrained(config('coupon.table_prefix') . 'coupons')->onDelete('cascade');
            $table->morphs('couponable');
            $table->integer('exclusion_type')->default(ExclusionType::INCLUDE->value);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('coupon.table_prefix') . 'couponables');
    }
};
