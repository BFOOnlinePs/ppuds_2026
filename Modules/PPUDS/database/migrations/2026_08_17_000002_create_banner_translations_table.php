<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'banner_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('banner_id');

            $table->foreign('banner_id', 'ppuds_banner_translations_banner_fk')
                ->references('id')
                ->on(config('ppuds.table_prefix') . 'banners')
                ->cascadeOnDelete();

            $table->string('locale')->index();
            $table->string('url');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'banner_translations');
    }
};
