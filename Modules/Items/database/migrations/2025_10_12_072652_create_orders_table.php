<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('items.table_prefix') . 'orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->index('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('order_number')->unique();
            $table->decimal('sub_total', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->integer('status')->default(OrderStatus::PENDING);
            $table->integer('payment_method')->default(PaymentMethod::CASH);
            $table->integer('payment_status')->default(PaymentStatus::UNPAID);
            $table->text('notes')->nullable();
            $table->text('delivery_address');
            $table->text('contact_phone');
            $table->foreignId('created_by')->index('created_by')->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'orders');
    }
};
