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
        Schema::create(config('content.table_prefix') . 'page_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('page_id')->index('page_id')->constrained(config('content.table_prefix') . 'pages')->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name');
            $table->longText('content');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('content.table_prefix') . 'page_translations');
    }
};
