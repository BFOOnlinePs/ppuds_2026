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
        $table = config('ppuds.table_prefix') . 'training_plan_translations';

        // المواد الجامعية المرتبطة بخطة التدريب، اختيارية حتى لا تتأثر الخطط الموجودة.
        if (! Schema::hasColumn($table, 'subjects')) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('subjects')->nullable()->after('outcomes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('ppuds.table_prefix') . 'training_plan_translations';

        if (Schema::hasColumn($table, 'subjects')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('subjects');
            });
        }
    }
};
