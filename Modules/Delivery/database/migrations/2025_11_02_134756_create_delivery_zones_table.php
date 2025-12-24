<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('delivery.table_prefix') . 'delivery_zones', function (Blueprint $table) {
            $table->id();

            // يربط بالفرع (المطعم)
            $table->foreignId('branch_id')->index('branch_id')->constrained(config('branch.table_prefix') . 'branches')->cascadeOnDelete();

            // يخزن "الرسم" (المضلع)
            $table->geometry('zone_area');

            // يربط بنموذج التسعير
            $table->foreignId('delivery_pricing_id')->constrained(config('delivery.table_prefix') . 'delivery_pricings');

            $table->boolean('is_active')->default(true);

            // فهرس مكاني للسرعة
            $table->spatialIndex('zone_area');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('delivery.table_prefix') . 'delivery_zones');
    }
};
