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
        Schema::create(config('ppuds.table_prefix') . 'note_translations', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('note_id')->index('note_id')->constrained(config('ppuds.table_prefix') . 'notes')->cascadeOnDelete();
            $table->string('locale', 5)->index('locale');
            $table->string('name');
            $table->text('content');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'note_translations');
    }
};
