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
        Schema::create(config('marketing.table_prefix') . 'loyalty_rule_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loyalty_rule_id')->constrained(config('marketing.table_prefix') . 'loyalty_rules')->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('marketing.table_prefix') . 'loyalty_rule_translations');
    }
};
