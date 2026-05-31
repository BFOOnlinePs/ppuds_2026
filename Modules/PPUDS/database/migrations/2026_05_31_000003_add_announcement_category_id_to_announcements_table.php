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
        Schema::table(config('ppuds.table_prefix') . 'announcements', function (Blueprint $table) {
            $table->foreignId('announcement_category_id')
                ->nullable()
                ->after('id')
                ->constrained(config('ppuds.table_prefix') . 'announcement_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('announcement_category_id');
        });
    }
};
