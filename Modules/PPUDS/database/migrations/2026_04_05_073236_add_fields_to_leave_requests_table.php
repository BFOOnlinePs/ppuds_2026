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
        Schema::table(config('ppuds.table_prefix') . 'leave_requests', function (Blueprint $table) {
            $table->foreignId('company_supervisor_id')->after('rejection_reason')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('university_supervisor_id')->after('company_supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('company_supervisor_comment')->after('university_supervisor_id')->nullable();
            $table->text('university_supervisor_comment')->after('company_supervisor_comment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'leave_requests', function (Blueprint $table) {
            $table->dropForeign(['company_supervisor_id']);
            $table->dropForeign(['university_supervisor_id']);

            $table->dropColumn([
                'company_supervisor_id',
                'university_supervisor_id',
                'company_supervisor_comment',
                'university_supervisor_comment'
            ]);
        });
    }
};
