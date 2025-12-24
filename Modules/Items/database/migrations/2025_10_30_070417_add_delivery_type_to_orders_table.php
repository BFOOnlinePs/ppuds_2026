<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Enums\DeliveryType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(config('items.table_prefix') . 'orders', function (Blueprint $table) {
            $table->integer('delivery_type')->default(DeliveryType::DELIVERY);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('items.table_prefix') . 'orders', function (Blueprint $table) {
            $table->dropColumn('delivery_type');
        });
    }
};
