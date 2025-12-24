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
        Schema::create(config('geolocation.table_prefix').'governorates', function (Blueprint $table) {
            $table->id();

            $table->string('code',10)->nullable();
            $table->foreignId('country_id')->index('country_id')->constrained(config('geolocation.table_prefix').'countries')->onDelete('cascade');
            $table->index(['code', 'country_id']);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // 2. الآن قم بحذف الجدول
        Schema::dropIfExists(config('geolocation.table_prefix').'governorates');

        // 3. أعد تفعيل التحقق
        Schema::enableForeignKeyConstraints();
    }
};
