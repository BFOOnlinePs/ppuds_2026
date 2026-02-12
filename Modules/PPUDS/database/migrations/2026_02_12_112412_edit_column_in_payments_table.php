<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'payments', function (Blueprint $table) {

            if (Schema::hasColumn(config('ppuds.table_prefix') . 'payments', 'student_notes')) {
                $table->renameColumn('student_notes', 'student_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'payments', function (Blueprint $table) {
            if (Schema::hasColumn(config('ppuds.table_prefix') . 'payments', 'student_role')) {
                $table->renameColumn('student_role', 'student_notes');
            }
        });
    }
};
