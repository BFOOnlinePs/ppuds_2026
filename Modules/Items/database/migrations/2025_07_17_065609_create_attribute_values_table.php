<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Enums\AttributeType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('items.table_prefix') . 'attribute_values', function (Blueprint $table) {
            $table->id();

            $table->string('color_code')->nullable();
            $table->decimal('numeric_value', 15, 4)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta_data')->nullable();
            $table->foreignId('attribute_id')
            ->index('attribute_id')
            ->constrained(config('items.table_prefix') . 'attributes')
            ->cascadeOnDelete();
            $table->foreignId('created_by')->index('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'attribute_values');
    }
};
