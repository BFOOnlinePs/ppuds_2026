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
        Schema::table('core_transactions', function (Blueprint $table) {
            $table->string('reference_no')->nullable()->index(); // رقم الإيصال، رقم الشيك، إلخ
            $table->unsignedTinyInteger('payment_method')->nullable(); // نوع الدفع
            $table->json('metadata')->nullable(); // نصيحة إضافية: حقل لتخزين تفاصيل إضافية
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core_transactions', function (Blueprint $table) {
            $table->dropColumn('reference_no');
            $table->dropColumn('payment_method');
            $table->dropColumn('metadata');
        });
    }
};
