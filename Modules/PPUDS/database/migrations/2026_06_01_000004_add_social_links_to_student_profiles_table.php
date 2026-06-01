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
        Schema::table(config('ppuds.table_prefix').'student_profiles', function (Blueprint $table) {
            $table->string('linkedin_url')->nullable()->after('major_id');
            $table->string('behance_url')->nullable()->after('linkedin_url');
            $table->string('github_url')->nullable()->after('behance_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix').'student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'linkedin_url',
                'behance_url',
                'github_url',
            ]);
        });
    }
};
