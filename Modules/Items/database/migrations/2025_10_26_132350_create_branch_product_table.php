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
        Schema::create(config('items.table_prefix') . 'branch_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained(config('branch.table_prefix') . 'branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained(config('items.table_prefix') . 'products')->cascadeOnDelete();

            $table->unique(['branch_id', 'product_id'], 'branch_product_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'branch_product');
    }
};
