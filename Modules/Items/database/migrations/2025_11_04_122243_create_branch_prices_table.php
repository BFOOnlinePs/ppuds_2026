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
        Schema::create(config('items.table_prefix') . 'branch_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->index('branch_id')->constrained(config('branch.table_prefix') . 'branches')->cascadeOnDelete();
            $table->foreignId('product_id')->index('product_id')->constrained(config('items.table_prefix') . 'products')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
//            $table->boolean('is_active')->default(true); // هل هو متاح في هذا الفرع؟
//            $table->integer('stock')->nullable(); // (اختياري) لإدارة المخزون لكل فرع

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'branch_prices');
    }
};
