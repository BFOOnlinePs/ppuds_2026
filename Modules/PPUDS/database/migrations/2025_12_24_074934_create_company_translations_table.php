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
        Schema::create(config('ppuds.table_prefix') . 'company_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained(config('ppuds.table_prefix') . 'companies')->cascadeOnDelete();
            $table->string('locale', 2)->index();
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
        Schema::dropIfExists(config('ppuds.table_prefix') . 'company_translations');
    }
};
