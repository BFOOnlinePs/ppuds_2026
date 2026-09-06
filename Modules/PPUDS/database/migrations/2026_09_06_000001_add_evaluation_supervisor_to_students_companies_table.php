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
        Schema::table(config('ppuds.table_prefix').'students_companies', function (Blueprint $table) {
            $table->foreignId('evaluation_supervisor_id')
                ->nullable()
                ->after('department_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedTinyInteger('evaluation_score')
                ->nullable()
                ->after('evaluation_supervisor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix').'students_companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluation_supervisor_id');
            $table->dropColumn('evaluation_score');
        });
    }
};
