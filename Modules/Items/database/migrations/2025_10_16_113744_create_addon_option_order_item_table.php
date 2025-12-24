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
        Schema::create(config('items.table_prefix') . 'addon_option_order_item', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_item_id')->constrained(config('items.table_prefix') . 'order_items')->cascadeOnDelete();
            $table->foreignId('addon_option_id')->constrained(config('items.table_prefix') . 'addon_options')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 8, 2);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'addon_option_order_item');
    }
};
