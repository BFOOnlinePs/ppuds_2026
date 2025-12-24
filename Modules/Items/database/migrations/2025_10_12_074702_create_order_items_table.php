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
        Schema::create(config('items.table_prefix') . 'order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->index('order_id')->constrained(config('items.table_prefix') . 'orders')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained(config('items.table_prefix') . 'products')->onDelete('set null');
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total_price', 10, 2);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'order_items');
    }
};
