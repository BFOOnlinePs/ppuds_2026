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
        Schema::create(config('items.table_prefix') . 'offerables', function (Blueprint $table) {

            $table->foreignId('offer_id')->index('offer_id')->constrained(config('items.table_prefix') . 'offers')->cascadeOnDelete();
            $table->morphs('offerable');
            $table->primary(['offer_id', 'offerable_id', 'offerable_type'] , 'offerables_primary_key');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('items.table_prefix') . 'offerables');
    }
};
