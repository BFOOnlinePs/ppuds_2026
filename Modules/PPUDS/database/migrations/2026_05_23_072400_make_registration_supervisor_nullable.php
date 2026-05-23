<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('ppuds.table_prefix') . 'registrations';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedBigInteger('supervisor_id')->nullable()->change();

            $table->foreign('supervisor_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $tableName = config('ppuds.table_prefix') . 'registrations';

        DB::table($tableName)
            ->whereNull('supervisor_id')
            ->update(['supervisor_id' => DB::raw('created_by')]);

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedBigInteger('supervisor_id')->nullable(false)->change();

            $table->foreign('supervisor_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
