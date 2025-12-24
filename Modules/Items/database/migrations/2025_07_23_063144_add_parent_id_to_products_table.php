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
        Schema::table(config('items.table_prefix') . 'products', function (Blueprint $table) {
            $table->integer('parent_id')->index('parent_id')->nullable()->constrained(config('items.table_prefix') . 'products')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('items.table_prefix') . 'products', function (Blueprint $table) {
            $table->dropColumn('parent_id');
        });
    }
};
