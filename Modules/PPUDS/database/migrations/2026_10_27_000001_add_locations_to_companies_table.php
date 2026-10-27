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

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'main_location')) {
                $table->string('main_location')
                    ->nullable()
                    ->after('contact_info');
            }

            if (! Schema::hasColumn($tableName, 'sub_location')) {
                $table->string('sub_location')
                    ->nullable()
                    ->after('main_location');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('ppuds.table_prefix').'companies';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            foreach (['main_location', 'sub_location'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
