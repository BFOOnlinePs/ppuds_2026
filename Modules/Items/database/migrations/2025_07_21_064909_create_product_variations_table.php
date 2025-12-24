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
        Schema::create(config('items.table_prefix') . 'product_variations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->index('product_id')
                ->constrained(config('items.table_prefix') . 'products')
                ->cascadeOnDelete();
            $table->foreignId('attribute_value_id')
                ->index('attribute_value_id')
                ->constrained(config('items.table_prefix') . 'attribute_values')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'product_variations');
    }
};
