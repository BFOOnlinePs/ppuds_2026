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
        Schema::create('core_transactions', function (Blueprint $table) {
            $table->id();

            // 1. القيمة والتدفق (أساس التقارير السريعة)
            $table->decimal('amount', 15, 2)->comment('قيمة المعاملة (بالموجب دائماً)');
            $table->enum('flow', ['INCOME', 'EXPENSE'])->comment('اتجاه الحركة: دخل (INCOME) أو مصروف/إرجاع (EXPENSE)');
            $table->dateTime('transaction_date')->comment('تاريخ ووقت حدوث المعاملة الفعلية');
            $table->string('description')->nullable()->comment('وصف موجز للحركة (مثلاً: تجديد اشتراك، دفعة مقدمة)');

            // 2. الربط بالمستندات المصدرية (Polymorphic Relation)
            // تربط السجل بـ Invoice أو Subscription
            $table->morphs('transactionable');

            // 3. تصنيف الموديول ونوع الحركة (مهم للتقارير المجمعة)
            $table->string('source_module')->comment('الموديول الذي أنشأ الحركة (Clinic, Subscription)');
            $table->string('source_type')->comment('نوع الحركة أو المستند (Renewal, Refund, ServiceFee, Prepayment)');

            // 4. الربط بالطرف المرتبط (المريض/العميل)
            // تربط السجل بـ Patient أو Customer
            $table->nullableMorphs('related_entity');

            // فهارس لتحسين تقارير الدخل اليومي والبحث
            $table->index(['transaction_date', 'flow', 'source_module']);

            $table->foreignId('created_by')->index('created_by')->constrained('users')->onDelete('cascade')->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_transactions');
    }
};
