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
            // علامة مشرف الجامعة. تُخزَّن هنا وليس في registrations.university_score
            // لأن مزامنة نظام الجامعة تكتب فوق ذلك العمود في كل تشغيل.
            $table->unsignedTinyInteger('supervisor_score')
                ->nullable()
                ->after('evaluation_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix').'students_companies', function (Blueprint $table) {
            $table->dropColumn('supervisor_score');
        });
    }
};
