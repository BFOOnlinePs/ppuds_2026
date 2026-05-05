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
        $tableName = config('ppuds.table_prefix').'companies';

        if (Schema::hasColumn($tableName, 'old_company_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedBigInteger('old_company_id')
                ->nullable()
                ->unique()
                ->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('ppuds.table_prefix').'companies';

        if (! Schema::hasColumn($tableName, 'old_company_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique(['old_company_id']);
            $table->dropColumn('old_company_id');
        });
    }
};
