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
        Schema::create(config('items.table_prefix') . 'addon_option_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('addon_option_id')->index('addon_option_id')->constrained(config('items.table_prefix') . 'addon_options')->cascadeOnDelete();
            $table->string('locale',5)->index();
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
        Schema::dropIfExists(config('items.table_prefix') . 'addon_option_translations');
    }
};
