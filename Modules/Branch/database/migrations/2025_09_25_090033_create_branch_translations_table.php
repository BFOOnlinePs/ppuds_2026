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
        Schema::create(config('branch.table_prefix') . 'branch_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained(config('branch.table_prefix') . 'branches');
            $table->string('locale')->index('locale');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('address')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('branch.table_prefix') . 'branch_translations');
    }
};
