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
        Schema::create(config('items.table_prefix').'products_tags', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained(config('items.table_prefix').'products')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained(config('items.table_prefix').'tags')->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix').'products_tags');
    }
};
