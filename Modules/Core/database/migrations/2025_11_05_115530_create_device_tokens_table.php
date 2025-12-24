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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->index('user_id')->constrained('users')->onDelete('cascade');

            // 2. الـ Token نفسه، ويجب أن يكون فريداً (unique)
            $table->string('token')->unique();

            // 3. (اختياري) اسم الجهاز أو نوعه (ios, android, web)
            $table->string('device_name')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
