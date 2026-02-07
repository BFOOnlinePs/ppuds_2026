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
        Schema::create(config('ppuds.table_prefix') . 'announcement_targets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('announcement_id')
                ->constrained(config('ppuds.table_prefix') . 'announcements')
                ->cascadeOnDelete();

            $table->string('user_role')->index();

            $table->unique(['announcement_id', 'user_role']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'announcement_targets');
    }
};
