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
        Schema::create(config('items.table_prefix') . 'categories', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();
            $table->integer('status')->default(1);
            $table->integer('sort_order')->default(0);
            $table->foreignId('parent_id')->index('parent_id')->nullable()->constrained(config('items.table_prefix') . 'categories')->nullOnDelete();
            $table->foreignId('created_by')->index('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['parent_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'categories');
    }
};
