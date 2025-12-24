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
        Schema::create(config('content.table_prefix') . 'banners', function (Blueprint $table) {
            $table->id();

            $table->string('url')->nullable();
            $table->boolean('active')->default(true);
            $table->morphs('bannable');
            $table->foreignId('created_by')->index('created_by')->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('content.table_prefix') . 'banners');
    }
};
