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
        Schema::create(config('delivery.table_prefix') . 'customer_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->index('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('address_details')->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->boolean('is_default')->default(false);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('delivery.table_prefix') . 'customer_addresses');
    }
};
