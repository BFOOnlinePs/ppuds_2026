<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف ناعم للمستخدمين.
 *
 * كان حذف المستخدم يمسح صفه نهائياً بلا إمكانية استرجاع. مع هذا العمود يبقى
 * الصف موجوداً ويُخفى فقط، فيمكن استعادته بكل أدواره وارتباطاته.
 *
 * يعمل مع الهجرة 2026_09_12_000001 التي حوّلت created_by وروابط المشرفين
 * إلى SET NULL: حتى لو حُذف مستخدم حذفاً نهائياً لاحقاً، تبقى سجلات العمل.
 *
 * تسجيل الدخول محمي تلقائياً: مزوّد مصادقة Eloquent يبني استعلامه من الموديل،
 * فيطبّق نطاق SoftDeletes العام ولا يجد المستخدم المحذوف.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'deleted_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'deleted_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
