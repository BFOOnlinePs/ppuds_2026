<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'alumni_announcement_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('alumni_announcement_id');

            $table->foreign('alumni_announcement_id', 'alumni_announcement_translations_announcement_fk')
                ->references('id')
                ->on(config('ppuds.table_prefix') . 'alumni_announcements')
                ->cascadeOnDelete();

            $table->string('locale')->index();

            $table->string('name');
            $table->text('content');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'alumni_announcement_translations');
    }
};
