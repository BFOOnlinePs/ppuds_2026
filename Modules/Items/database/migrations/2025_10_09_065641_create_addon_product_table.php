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
        Schema::create(config('items.table_prefix') . 'addon_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('addon_id')->index('addon_id')->constrained(config('items.table_prefix') . 'addons')->cascadeOnDelete();
            $table->foreignId('product_id')->index('product_id')->constrained(config('items.table_prefix') . 'products')->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('min_selection')->default(1);
            $table->unsignedInteger('max_selection')->nullable();
            $table->unsignedInteger('max_quantity_per_option')->default(1);
            $table->foreignId('created_by')->index('created_by')->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'addon_product');
    }
};
