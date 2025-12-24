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
        Schema::create(config('content.table_prefix') . 'banner_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('banner_id')->index('banner_id')->constrained(config('content.table_prefix') . 'banners')->cascadeOnDelete();
            $table->string('locale')->index('locale');
            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('content.table_prefix') . 'banner_translations');
    }
};
