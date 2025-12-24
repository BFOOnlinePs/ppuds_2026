<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Customer\Enums\GenderType;
use Modules\Customer\Enums\Status;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('clinic.table_prefix') . 'customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->date('date_of_birth')->nullable();
            $table->integer('gender')->default(GenderType::MALE->value);
            $table->text('address')->nullable();
            $table->foreignId('district_id')->index('district_id')->constrained(config('geolocation.table_prefix') . 'districts')->onDelete('cascade');
            $table->foreignId('city_id')->index('city_id')->constrained(config('geolocation.table_prefix') . 'cities')->onDelete('cascade');
            $table->foreignId('governorate_id')->index('governorate_id')->constrained(config('geolocation.table_prefix') . 'governorates')->onDelete('cascade');
            $table->foreignId('country_id')->index('country_id')->constrained(config('geolocation.table_prefix') . 'countries')->onDelete('cascade');
            $table->integer('status')->default(Status::ACTIVE->value);
            $table->text('notes')->nullable();
            $table->string('language' , 2)->default('ar')->nullable();
            $table->foreignId('created_by')->index('user_id')->constrained('users')->onDelete('cascade');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('clinic.table_prefix') . 'customer_profiles');
    }
};
