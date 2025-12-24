<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Enums\ProductActive;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(config('items.table_prefix') . 'products', function (Blueprint $table) {
            $table->integer('is_active')->default(ProductActive::IS_ACTIVE->value);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('items.table_prefix') . 'products', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
