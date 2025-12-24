<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Marketing\Enums\LoyaltyRuleType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('marketing.table_prefix') . 'loyalty_rules', function (Blueprint $table) {
            $table->id();

            $table->string('module')->index();
            $table->string('action')->index();
            $table->string('type')->default(LoyaltyRuleType::BASE_RATE->value);
            $table->decimal('points_rate', 8, 2)->nullable();
            $table->unsignedInteger('fixed_points')->nullable();
            $table->decimal('min_amount', 10, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->index('created_by')->nullable()->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('marketing.table_prefix') . 'loyalty_rules');
    }
};
