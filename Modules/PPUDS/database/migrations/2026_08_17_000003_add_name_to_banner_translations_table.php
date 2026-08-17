<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'banner_translations', function (Blueprint $table) {
            $table->string('name')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix') . 'banner_translations', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
