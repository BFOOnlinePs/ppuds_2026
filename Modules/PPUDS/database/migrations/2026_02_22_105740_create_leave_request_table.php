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
        Schema::create(config('ppuds.table_prefix') . 'leave_request', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('student_company_id')->constrained(config('ppuds.table_prefix') . 'student_companies')->cascadeOnDelete();
            $table->integer();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'leave_request');
    }
};
