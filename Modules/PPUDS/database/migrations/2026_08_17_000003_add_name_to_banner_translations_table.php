<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `name` column ships in the create-table migration, so databases that
     * were built after it was added already have the column. This migration
     * only backfills the databases that were migrated before then, hence the
     * existence checks.
     */
    public function up(): void
    {
        $table = config('ppuds.table_prefix') . 'banner_translations';

        if (Schema::hasColumn($table, 'name')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->string('name')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        $table = config('ppuds.table_prefix') . 'banner_translations';

        if (! Schema::hasColumn($table, 'name')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
