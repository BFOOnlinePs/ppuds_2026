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
        Schema::table(config('clinic.table_prefix') . 'readings', function (Blueprint $table) {
            $table->decimal('water', 5, 2)->nullable()->after('salts');
            $table->decimal('bmi', 5, 2)->nullable()->after('water');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('clinic.table_prefix') . 'readings', function (Blueprint $table) {
            $table->dropColumn('water');
            $table->dropColumn('bmi');
        });
    }
};
