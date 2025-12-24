<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Enums\BranchStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('branch.table_prefix') . 'branches', function (Blueprint $table) {
            $table->id();

            $table->text('phone')->nullable();
            $table->text('email')->nullable();
            $table->foreignId('city_id')->index('city_id')->constrained(config('geolocation.table_prefix') . 'cities')->cascadeOnDelete();
            $table->foreignId('country_id')->index('country_id')->constrained(config('geolocation.table_prefix') . 'countries')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->integer('status')->default(BranchStatus::OPEN->value);
            $table->foreignId('created_by')->index('created_by')->constrained('users')->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('branch.table_prefix') . 'branches');
    }
};
