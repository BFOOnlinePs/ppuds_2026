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
        Schema::create(config('items.table_prefix') . 'category_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->index('category_id')
                ->constrained(config('items.table_prefix') . 'categories')
                ->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->text('description')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['category_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'category_translations');
    }
};
