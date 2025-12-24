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
        Schema::create(config('items.table_prefix') . 'addon_product_option', function (Blueprint $table) {
            $table->id();

            $table->foreignId('addon_product_id')
                ->constrained(config('items.table_prefix') . 'addon_product')
                ->cascadeOnDelete();

            $table->foreignId('addon_option_id')
                ->constrained(config('items.table_prefix') . 'addon_options')
                ->cascadeOnDelete();

            $table->unique(['addon_product_id', 'addon_option_id'], 'addon_product_option_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'addon_product_option');
    }
};
