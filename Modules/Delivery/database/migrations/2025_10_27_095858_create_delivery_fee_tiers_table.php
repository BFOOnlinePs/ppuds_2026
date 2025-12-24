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
        Schema::create(config('delivery.table_prefix') . 'delivery_fee_tiers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_pricing_id')->index('delivery_pricing_id')->constrained(config('delivery.table_prefix') . 'delivery_pricings')->cascadeOnDelete();
            $table->decimal('min_distance_km', 8, 2)
                ->comment('الحد الأدنى للمسافة لتطبيق هذه الشريحة (مثال: 5 كم)');
            $table->decimal('extra_charge', 8, 2)
                ->comment('المبلغ الإضافي الذي يضاف "فوق" السعر الأساسي عند تجاوز الحد');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('delivery.table_prefix') . 'delivery_fee_tiers');
    }
};
