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
        Schema::table(config('items.table_prefix') . 'addon_product_option', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('addon_option_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('items.table_prefix') . 'addon_product_option', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
