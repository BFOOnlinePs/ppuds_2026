<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Enums\ProductStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('items.table_prefix') . 'products', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();
            $table->string('barcode')->index('barcode')->nullable()->uniqid();
            $table->string('sku')->unique();
            $table->float('discount')->default(0);

            // Pricing
            $table->decimal('base_price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable(); // سعر التكلفة
            $table->decimal('wholesale_price', 10, 2)->nullable(); // سعر الجملة

            // Inventory
            $table->integer('stock_quantity')->default(0);
            $table->boolean('manage_stock')->default(true);
            $table->integer('low_stock_threshold')->default(10); // تنبيه انخفاض المخزون
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])->default('in_stock');
            $table->boolean('allow_backorder')->default(false); // السماح بالطلب المسبق
            $table->integer('max_quantity_per_order')->nullable(); // الحد الأقصى للكمية في الطلب الواحد

            // Discount Settings
            $table->enum('discount_type', ['none', 'percentage', 'fixed'])->default('none');
            $table->decimal('discount_value', 8, 2)->nullable();
            $table->datetime('discount_start_date')->nullable();
            $table->datetime('discount_end_date')->nullable();
            $table->integer('min_quantity_discount')->nullable(); // الحد الأدنى للكمية للحصول على خصم

            $table->decimal('weight', 8, 2)->nullable();
            $table->integer('status')->default(ProductStatus::DRAFT);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_digital')->default(false);
            $table->json('meta_data')->nullable();
            $table->foreignId('created_by')->index('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['is_featured', 'status']);
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'products');
    }
};
