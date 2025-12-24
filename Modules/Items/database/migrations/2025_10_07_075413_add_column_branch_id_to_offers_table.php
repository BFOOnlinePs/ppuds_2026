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
        Schema::table(config('items.table_prefix') . 'offers', function (Blueprint $table) {
            $table->foreignId('branch_id')->after('id')->index('branch_id')->nullable()->constrained(config('branch.table_prefix') . 'branches')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('items.table_prefix') . 'offers', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);

            // 2. احذف العمود (سيتم حذف الفهرس المرتبط به تلقائياً)
            $table->dropColumn('branch_id');
        });
    }
};
