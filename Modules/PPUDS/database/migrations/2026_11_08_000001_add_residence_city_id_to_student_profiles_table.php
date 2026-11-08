<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مدينة سكن الطالب — يستخدمها مساعد الذكاء الاصطناعي لاقتراح أقرب شركة.
     */
    public function up(): void
    {
        $tableName = config('ppuds.table_prefix').'student_profiles';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'residence_city_id')) {
                $table->foreignId('residence_city_id')
                    ->nullable()
                    ->after('major_id')
                    ->constrained(config('geolocation.table_prefix').'cities')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('ppuds.table_prefix').'student_profiles';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'residence_city_id')) {
                $table->dropConstrainedForeignId('residence_city_id');
            }
        });
    }
};
