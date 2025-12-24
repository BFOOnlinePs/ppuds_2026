<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Subscription\Enums\Status;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('subscription.table_prefix') . 'subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->index('customer_id')->constrained(config('clinic.table_prefix') . 'customer_profiles');
            $table->foreignId('plan_id')->index('plan_id')->constrained(config('subscription.table_prefix') . 'plans')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('status')->default(Status::ACTIVE->value);
            $table->decimal('paid_amount', 8, 2)->default(0);
            $table->integer('remaining_visits')->nullable();
            $table->foreignId('created_by')->index('created_by')->constrained('users');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('subscription.table_prefix') . 'subscriptions');
    }
};
