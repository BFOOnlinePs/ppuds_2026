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
        Schema::create(config('delivery.table_prefix') . 'delivery_pricing_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_id')->index('delivery_pricing_id')->constrained(config('delivery.table_prefix') . 'delivery_pricings')->cascadeOnDelete();
            $table->string('locale')->index('locale');
            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('delivery.table_prefix') . 'delivery_pricing_translations');
    }
};
