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
        Schema::create(config('clinic.table_prefix') . 'readings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->index('customer_id')->constrained(config('clinic.table_prefix') . 'customer_profiles');
            $table->decimal('weight');
            $table->decimal('fats');
            $table->decimal('muscles');
            $table->decimal('salts')->nullable();
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
        Schema::dropIfExists(config('clinic.table_prefix') . 'readings');
    }
};
