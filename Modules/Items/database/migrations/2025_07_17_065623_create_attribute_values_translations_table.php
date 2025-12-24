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
        Schema::create(config('items.table_prefix') . 'attribute_values_translations', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('description')->nullable();
            $table->string('locale', 5)->index();
            $table->foreignId('attribute_value_id')
                ->index('attribute_value_id')
                ->constrained(config('items.table_prefix') . 'attribute_values')
                ->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'attribute_values_translations');
    }
};
