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
        Schema::create(config('delivery.table_prefix') . 'delivery_pricings', function (Blueprint $table) {
            $table->id();

            $table->decimal('base_fee', 8, 2)->default(0)
                ->comment('السعر الأساسي للتوصيل (أقل سعر)');
            $table->decimal('price_per_km', 8, 2)->default(0)
                ->comment('السعر الإضافي لكل كيلومتر');
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
        Schema::dropIfExists(config('delivery.table_prefix') . 'delivery_pricings');
    }
};
