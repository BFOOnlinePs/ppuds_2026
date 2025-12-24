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
        Schema::table(config('delivery.table_prefix') . 'customer_addresses', function (Blueprint $table) {
            $table->string('contact_phone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('delivery.table_prefix') . 'customer_addresses', function (Blueprint $table) {
            $table->dropColumn('contact_phone');
        });
    }
};
