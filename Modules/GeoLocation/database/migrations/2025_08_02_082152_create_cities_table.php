<?php

use Modules\GeoLocation\Enums\CityType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\GeoLocation\Enums\CapitalType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('geolocation.table_prefix') . 'cities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('governorate_id')->index('governorate_id')->constrained(config('geolocation.table_prefix') . 'governorates')->onDelete('cascade');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('population')->nullable();
            $table->integer('type')->index('type')->default(CityType::CITY->value());
            $table->boolean('is_capital')->index('is_capital')->default(false);
            $table->integer('capital_type')->nullable();
            $table->index(['governorate_id', 'type']);
            $table->index(['latitude', 'longitude']);

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
        Schema::dropIfExists(config('geolocation.table_prefix') . 'cities');

        // 3. أعد تفعيل التحقق
        Schema::enableForeignKeyConstraints();
    }
};
