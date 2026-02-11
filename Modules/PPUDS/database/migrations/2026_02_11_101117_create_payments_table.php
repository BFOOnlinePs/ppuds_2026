<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\PPUDS\Enums\PaymentStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_company_id')
                ->constrained(config('ppuds.table_prefix') . 'students_companies')
                ->constrained(config('ppuds.table_prefix') . 'students_companies')
                ->cascadeOnDelete();
            $table->string('reference_id')->nullable()->index();
            $table->decimal('payment_value', 10, 2);
            $table->text('student_notes')->nullable();
            $table->text('company_notes')->nullable();
            $table->integer('status')->default(PaymentStatus::UNPAID->value);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->index()->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'payments');
    }
};
