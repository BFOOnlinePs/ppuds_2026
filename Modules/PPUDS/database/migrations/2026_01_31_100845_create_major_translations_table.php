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
        Schema::create(config('ppuds.table_prefix') . 'major_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('major_id')->index('major_id')->constrained(config('ppuds.table_prefix') . 'majors')->cascadeOnDelete();
            $table->string('locale')->index('locale');
            $table->string('name');
            $table->string('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'major_translations');
    }
};
