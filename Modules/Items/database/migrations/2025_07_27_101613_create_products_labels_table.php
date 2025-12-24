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
        Schema::create(config('items.table_prefix').'products_labels', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained(config('items.table_prefix').'products')->cascadeOnDelete();
            $table->foreignId('label_id')->constrained(config('items.table_prefix').'labels')->cascadeOnDelete();
            $table->primary(['product_id', 'label_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix').'products_labels');
    }
};
