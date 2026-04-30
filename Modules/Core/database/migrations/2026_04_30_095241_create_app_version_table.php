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
        Schema::create('app_version', function (Blueprint $table) {
            $table->id();

            $table->string('platform')->unique();
            $table->string('min_version');
            $table->string('latest_version');
            $table->string('store_url');
            $table->boolean('maintenance_mode')->default(false);
            $table->text('message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_version');
    }
};
