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
        Schema::create(config('items.table_prefix') . 'offer_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offer_id')->index('offer_id')->constrained(config('items.table_prefix') . 'offers')->cascadeOnDelete();
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
        Schema::dropIfExists(config('items.table_prefix') . 'offer_translations');
    }
};
