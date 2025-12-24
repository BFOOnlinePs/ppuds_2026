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
        Schema::create('currency_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('currency_id')->constrained('currencies')->onDelete('cascade');
            $table->string('locale', 5);
            $table->string('name');
            $table->string('plural_name')->nullable();

            $table->timestamps();

            $table->unique(['currency_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_translations');
    }
};
