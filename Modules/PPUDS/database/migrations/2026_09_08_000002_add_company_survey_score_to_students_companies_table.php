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
            // علامة الشركة المحسوبة من استبيان مشرف الشركة. تُخزَّن هنا وليس في
            // registrations.company_score لأن مزامنة نظام الجامعة تكتب فوق ذلك
            // العمود في كل تشغيل. عشرية لأنها نسبة من علامة الشركة الكاملة.
            $table->float('company_survey_score')
                ->nullable()
                ->after('supervisor_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix').'students_companies', function (Blueprint $table) {
            $table->dropColumn('company_survey_score');
        });
    }
};
