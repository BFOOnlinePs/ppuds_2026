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
            if (! Schema::hasColumn($tableName, 'contact_person')) {
                $table->string('contact_person')
                    ->nullable()
                    ->after('website');
            }

            if (! Schema::hasColumn($tableName, 'contact_info')) {
                $table->text('contact_info')
                    ->nullable()
                    ->after('contact_person');
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
            foreach (['contact_person', 'contact_info'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
