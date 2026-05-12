<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'student_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester_level')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('ppuds.table_prefix') . 'student_profiles';

        DB::table($table)
            ->whereNull('semester_level')
            ->update(['semester_level' => 1]);

        Schema::table($table, function (Blueprint $table) {
            $table->unsignedTinyInteger('semester_level')->nullable(false)->default(1)->change();
        });
    }
};
