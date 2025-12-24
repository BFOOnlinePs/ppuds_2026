<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\GeoLocation\Enums\DistrictType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('geolocation.table_prefix') . 'districts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('city_id')->index('city_id')->constrained(config('geolocation.table_prefix') . 'cities');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('type')->index('type')->default(DistrictType::DISTRICT->value());

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. إيقاف التحقق من المفاتيح الخارجية مؤقتاً
        Schema::disableForeignKeyConstraints();

        // 2. الآن قم بحذف الجدول
        Schema::dropIfExists(config('geolocation.table_prefix') . 'districts');

        // 3. أعد تفعيل التحقق
        Schema::enableForeignKeyConstraints();
    }
};
