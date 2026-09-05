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
        $tableName = config('branch.table_prefix') . 'branches';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'manager_name')) {
                $table->string('manager_name')
                    ->nullable()
                    ->after('phone');
            }

            if (! Schema::hasColumn($tableName, 'manager_phone')) {
                $table->string('manager_phone', 50)
                    ->nullable()
                    ->after('manager_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('branch.table_prefix') . 'branches';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            foreach (['manager_name', 'manager_phone'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
