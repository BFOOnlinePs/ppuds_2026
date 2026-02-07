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
        Schema::create(config('ppuds.table_prefix') . 'announcement_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('announcement_id')
                ->constrained(config('ppuds.table_prefix') . 'announcements')
                ->cascadeOnDelete();

            $table->string('locale')->index();

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
        Schema::dropIfExists(config('ppuds.table_prefix') . 'announcement_translations');
    }
};
