<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يمنع حذف مستخدم من إسقاط بيانات العمل معه.
 *
 * كانت هذه الأعمدة ON DELETE CASCADE، فحذف موظف واحد كان يمحو ما أنشأه
 * وبالتسلسل ما يتعلق به. أخطر سلسلة:
 *
 *   حذف من أنشأ مساقاً → يُحذف المساق → تُحذف كل التسجيلات عليه
 *   → تُحذف كل سجلات التدريب → يُمحى الحضور والزيارات والإجازات
 *     والدفعات وساعات الدوام والتقارير النهائية.
 *
 * created_by وروابط المشرفين بيانات توثيق لا مِلكية، فتصبح SET NULL
 * ليبقى السجل وتضيع نسبته لصاحبه فقط.
 *
 * أعمدة المِلكية الحقيقية (student_id، وملف الطالب وخبراته وأجهزته)
 * تبقى CASCADE عمداً: حذف الطالب يحذف بياناته الشخصية.
 *
 * مكتوبة بـ information_schema لا بـ dropForeign، لأن قواعد البيانات
 * الحقيقية تختلف عن المحلية: بعض القيود مفقودة وبعضها بأسماء غير قياسية،
 * و dropForeign يخمّن الاسم فيفشل. وهي قابلة لإعادة التشغيل بأمان، إذ
 * تتخطى ما تم تحويله سابقاً — فلا يضرّ توقّفها في المنتصف.
 */
return new class extends Migration
{
    /**
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

            $definition = $this->columnDefinition($table, $column);

            if (! $definition) {
                continue;
            }

            $foreignKey = $this->foreignKeyToUsers($table, $column);

            // تم تحويله سابقاً — تخطٍّ يجعل إعادة التشغيل آمنة
            if ($foreignKey && $foreignKey->delete_rule === 'SET NULL' && $definition->is_nullable === 'YES') {
                continue;
            }

            if ($foreignKey) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey->constraint_name}`");
            }

            if ($definition->is_nullable === 'NO') {
                // النوع يُقرأ كما هو من الجدول حتى لا تُفقد دقّته عند التعديل
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition->column_type} NULL");
            }

            $this->nullifyOrphans($table, $column);

            $name = $foreignKey->constraint_name ?? $table.'_'.$column.'_foreign';

            DB::statement(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` ".
                "FOREIGN KEY (`{$column}`) REFERENCES `users` (`id`) ON DELETE SET NULL"
            );
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

            $foreignKey = $this->foreignKeyToUsers($table, $column);

            if (! $foreignKey || $foreignKey->delete_rule === 'CASCADE') {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey->constraint_name}`");

            // CASCADE لا يقبل صفوفاً يتيمة، والقيمة الفارغة لا مرجع لها أصلاً
            DB::table($table)->whereNull($column)->delete();

            DB::statement(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$foreignKey->constraint_name}` ".
                "FOREIGN KEY (`{$column}`) REFERENCES `users` (`id`) ON DELETE CASCADE"
            );
        }
    }

    /**
     * القيد الفعلي المرتبط بجدول users — قد يكون مفقوداً أو باسم غير قياسي.
     */
    private function foreignKeyToUsers(string $table, string $column): ?object
    {
        return DB::selectOne(
            'SELECT kcu.CONSTRAINT_NAME AS constraint_name, rc.DELETE_RULE AS delete_rule
             FROM information_schema.KEY_COLUMN_USAGE kcu
             JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
               ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
              AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
             WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
               AND kcu.TABLE_NAME = ?
               AND kcu.COLUMN_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME = ?
             LIMIT 1',
            [$table, $column, 'users']
        );
    }

    private function columnDefinition(string $table, string $column): ?object
    {
        return DB::selectOne(
            'SELECT COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    /**
     * قيم تشير إلى مستخدمين لم يعودوا موجودين. هي مؤشرات معلّقة بلا معنى
     * أصلاً، وبقاؤها يمنع إنشاء القيد — فتُفرَّغ ويبقى السجل كما هو.
     */
    private function nullifyOrphans(string $table, string $column): void
    {
        DB::statement(
            "UPDATE `{$table}` t
             LEFT JOIN `users` u ON u.`id` = t.`{$column}`
             SET t.`{$column}` = NULL
             WHERE t.`{$column}` IS NOT NULL AND u.`id` IS NULL"
        );
    }
};
