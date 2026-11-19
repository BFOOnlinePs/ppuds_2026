<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * notified_at marks the announcement whose notification has gone out, so
     * a scheduled announcement is notified once, when it is published.
     */
    public function up(): void
    {
        $table = config('ppuds.table_prefix') . 'announcements';

        Schema::table($table, function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('expires_at')->index();
        });

        // Existing announcements were notified when they were created.
        DB::table($table)->update(['notified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'announcements', function (Blueprint $table) {
            $table->dropIndex(['notified_at']);
            $table->dropColumn('notified_at');
        });
    }
};
