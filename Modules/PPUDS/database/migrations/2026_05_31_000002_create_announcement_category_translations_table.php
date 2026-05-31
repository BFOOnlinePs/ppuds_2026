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
        Schema::create(config('ppuds.table_prefix') . 'announcement_category_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('announcement_category_id')->index('ann_cat_trans_cat_id_index');
            $table->foreign('announcement_category_id', 'ann_cat_trans_cat_id_foreign')
                ->references('id')
                ->on(config('ppuds.table_prefix') . 'announcement_categories')
                ->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name')->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'announcement_category_translations');
    }
};
