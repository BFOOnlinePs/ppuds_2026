<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Items\Enums\AddonOptionStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('items.table_prefix') . 'addon_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('addon_id')->index('addon_id')->constrained(config('items.table_prefix') . 'addons')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('status')->default(AddonOptionStatus::ACTIVE->value);
            $table->boolean('is_quantifiable')->default(false);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'addon_options');
    }
};
