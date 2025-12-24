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
        Schema::create(config('items.table_prefix').'labels', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();
            $table->string('text_color')->default('#ffffff');
            $table->string('background_color')->default('#000000');
            $table->string('icon')->nullable();
            $table->foreignId('created_by')->index('created_by')->nullable()->constrained('users')->onDelete('cascade');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix').'labels');
    }
};
