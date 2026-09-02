<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alt text belongs on the media row itself, where it covers every file the
     * library lists and not only the ones uploaded through it.
     */
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropColumn('alt_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('alt_text')->nullable()->after('id');
        });
    }
};
