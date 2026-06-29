<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ppuds.table_prefix').'student_attendances', function (Blueprint $table) {
            $table->index(['attendance_date', 'student_company_id'], 'ppuds_attendance_map_date_company_idx');
            $table->index(['attendance_date', 'check_in_latitude', 'check_in_longitude'], 'ppuds_attendance_map_check_in_idx');
            $table->index(['attendance_date', 'check_out_latitude', 'check_out_longitude'], 'ppuds_attendance_map_check_out_idx');
        });
    }

    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix').'student_attendances', function (Blueprint $table) {
            $table->dropIndex('ppuds_attendance_map_date_company_idx');
            $table->dropIndex('ppuds_attendance_map_check_in_idx');
            $table->dropIndex('ppuds_attendance_map_check_out_idx');
        });
    }
};
