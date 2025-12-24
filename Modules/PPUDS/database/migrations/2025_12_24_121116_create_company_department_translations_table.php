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
        Schema::create(config('ppuds.table_prefix') . 'company_department_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_id')->index('department_id')->constrained(config('ppuds.table_prefix') . 'company_departments')->cascadeOnDelete();
            $table->string('locale')->index('locale');
            $table->string('name');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'company_department_translations');
    }
};
