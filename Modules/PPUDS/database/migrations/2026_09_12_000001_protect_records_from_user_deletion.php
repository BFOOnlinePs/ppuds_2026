<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * يمنع حذف مستخدم من إسقاط بيانات العمل معه.
 *
 * كانت كل هذه الأعمدة ON DELETE CASCADE، فحذف موظف واحد كان يمحو ما أنشأه،
 * وبالتسلسل ما يتعلق به. أخطر سلسلة كانت:
 *
 *   حذف من أنشأ مساقاً → يُحذف المساق → تُحذف كل التسجيلات عليه
 *   → تُحذف كل سجلات التدريب → يُمحى الحضور والزيارات والإجازات
 *     والدفعات وساعات الدوام والتقارير النهائية.
 *
 * الحل: created_by وروابط المشرفين بيانات توثيق لا مِلكية، فتصبح SET NULL
 * حتى يبقى السجل وتضيع نسبته لصاحبها فقط.
 *
 * ملاحظة: أعمدة المِلكية الحقيقية (student_id, user_id على ملف الطالب
 * وخبراته وأجهزته) تبقى CASCADE عمداً — حذف الطالب يحذف بياناته الشخصية.
 */
return new class extends Migration
{
    /**
     * الأعمدة المشمولة: [الجدول, العمود].
     * أسماء الجداول مكتوبة كاملة لأن بعضها خارج وحدة PPUDS،
     * وسلسلة الحذف تعبر بين الوحدات (branch_branches تُغذّي branch_department).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function columns(): array
    {
        $prefix = config('ppuds.table_prefix');

        return [
            // توثيق المُنشئ — لا يجوز أن يُسقط السجل نفسه
            ['branch_branches', 'created_by'],
            ['core_transactions', 'created_by'],
            [$prefix.'announcement_categories', 'created_by'],
            [$prefix.'companies', 'created_by'],
            [$prefix.'company_categories', 'created_by'],
            [$prefix.'company_departments', 'created_by'],
            [$prefix.'courses', 'created_by'],
            [$prefix.'final_reports', 'created_by'],
            [$prefix.'leave_requests', 'created_by'],
            [$prefix.'majors', 'created_by'],
            [$prefix.'notes', 'created_by'],
            [$prefix.'payments', 'created_by'],
            [$prefix.'registrations', 'created_by'],
            [$prefix.'student_attendances', 'created_by'],
            [$prefix.'student_reports', 'created_by'],
            [$prefix.'surveys', 'created_by'],
            [$prefix.'work_experiences', 'created_by'],

            // زيارات المشرف الميدانية — سجل عمل موثّق، لا يُمحى برحيله
            [$prefix.'field_visits', 'supervisor_id'],

            // مقعد مشرف الشركة — حذفه كان يُسقط صف الربط فيختفي
            // الطلاب عن كل مشرفي الشركة بلا أثر يدل على السبب
            [$prefix.'branch_department', 'user_id'],
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->unsignedBigInteger($column)->nullable()->change();

                $blueprint->foreign($column)
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * يعيد سلوك CASCADE السابق، لكنه يُبقي الأعمدة nullable عمداً:
     * إعادتها NOT NULL تتطلب حذف أو إعادة نسب كل صف فقد صاحبه،
     * وذلك إتلاف للبيانات — وهو نقيض الغرض من هذه الهجرة.
     */
    public function down(): void
    {
        foreach ($this->columns() as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->foreign($column)
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }
    }
};
