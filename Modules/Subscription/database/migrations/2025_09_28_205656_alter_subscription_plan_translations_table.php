<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table(config('subscription.table_prefix') . 'plan_translations', function (Blueprint $table) {
            // إزالة المفتاح الأجنبي القديم
            $table->dropForeign(['plan_id']);

            // إضافة المفتاح الأجنبي من جديد مع onDelete cascade
            $table->foreign('plan_id')
                ->references('id')
                ->on(config('subscription.table_prefix') . 'plans')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table(config('subscription.table_prefix') . 'plan_translations', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);

            $table->foreign('plan_id')
                ->references('id')
                ->on(config('subscription.table_prefix') . 'plans')
                ->onDelete('restrict');
        });
    }
};
