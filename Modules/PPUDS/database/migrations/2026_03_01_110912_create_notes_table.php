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
        Schema::create(config('ppuds.table_prefix') . 'notes', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')->index('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('note_date')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('created_by')->index('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'notes');
    }
};
