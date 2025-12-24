<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Enums\AddonType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('items.table_prefix') . 'addons', function (Blueprint $table) {
            $table->id();

            $table->integer('type')->default(AddonType::RADIO);
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
        Schema::dropIfExists(config('items.table_prefix') . 'addons');
    }
};
