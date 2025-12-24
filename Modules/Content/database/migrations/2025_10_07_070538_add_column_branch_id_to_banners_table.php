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
        Schema::table(config('content.table_prefix') . 'banners', function (Blueprint $table) {

            // "constrained()" تنشئ الفهرس تلقائياً
            // لا داعي لكتابة ->index('branch_id')

            $table->foreignId('branch_id')
                ->after('id')
                ->nullable()
                ->constrained(config('branch.table_prefix') . 'branches')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('content.table_prefix') . 'banners', function (Blueprint $table) {

            // 1. احذف المفتاح الخارجي (بالاسم الافتراضي الذي أنشأه constrained)
            $table->dropForeign(['branch_id']);

            // 2. احذف العمود (سيتم حذف الفهرس المرتبط به تلقائياً)
            $table->dropColumn('branch_id');
        });
    }
};
