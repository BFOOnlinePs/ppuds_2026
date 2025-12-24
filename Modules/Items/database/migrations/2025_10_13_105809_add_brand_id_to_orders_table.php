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
        Schema::table(config('items.table_prefix') . 'orders', function (Blueprint $table) {
            $table->foreignId('branch_id')->index('branch_id')->nullable()->constrained(config('branch.table_prefix') . 'branches')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('items.table_prefix') . 'orders', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);

            $table->dropIndex('branch_id');

            $table->dropColumn('branch_id');
        });
    }
};
