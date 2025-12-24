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
        Schema::table(config('clinic.table_prefix') . 'program_days', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('clinic.table_prefix') . 'program_days', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable(false)->change();
        });
    }
};
