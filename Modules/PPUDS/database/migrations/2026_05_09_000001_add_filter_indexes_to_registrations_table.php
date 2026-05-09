<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'registrations', function (Blueprint $table) {
            $table->index(['year', 'semester', 'deleted_at'], 'registrations_year_semester_deleted_idx');
            $table->index(['year', 'semester', 'course_id', 'deleted_at'], 'registrations_year_semester_course_deleted_idx');
            $table->index(['year', 'semester', 'supervisor_id', 'deleted_at'], 'registrations_year_semester_supervisor_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'registrations', function (Blueprint $table) {
            $table->dropIndex('registrations_year_semester_deleted_idx');
            $table->dropIndex('registrations_year_semester_course_deleted_idx');
            $table->dropIndex('registrations_year_semester_supervisor_deleted_idx');
        });
    }
};
