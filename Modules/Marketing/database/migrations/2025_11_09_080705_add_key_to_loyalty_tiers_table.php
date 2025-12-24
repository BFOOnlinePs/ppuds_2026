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
        Schema::table(config('marketing.table_prefix') . 'loyalty_tiers', function (Blueprint $table) {
            $table->string('key')->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('marketing.table_prefix') . 'loyalty_tiers', function (Blueprint $table) {
            $table->dropColumn('key');
        });
    }
};
