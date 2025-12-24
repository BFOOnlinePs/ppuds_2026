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
        Schema::create(config('geolocation.table_prefix') . 'countries', function (Blueprint $table) {
            $table->id();

            $table->string('code',2)->unique();
            $table->string('phone_code',10)->nullable();
            $table->foreignId('currency_id')->index('currency_id')->constrained('currencies')->onDelete('cascade');

            $table->softDeletes();
            $table->timestamps();

            $table->index('code');
            $table->index('phone_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // 2. الآن قم بحذف الجدول
        Schema::dropIfExists(config('geolocation.table_prefix').'countries');

        // 3. أعد تفعيل التحقق
        Schema::enableForeignKeyConstraints();
    }
};
