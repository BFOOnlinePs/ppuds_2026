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
        Schema::table(config('ppuds.table_prefix') . 'company_departments', function (Blueprint $table) {
            $table->foreignId('branch_id')->index('branch_id')->constrained(config('branch.table_prefix') . 'branches')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'company_departments', function (Blueprint $table) {
            $table->dropForeign('branch_id');
        });
    }
};
