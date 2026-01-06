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
        Schema::create(config('ppuds.table_prefix') . 'branch_company', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained(config('ppuds.table_prefix') . 'companies')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained(config('branch.table_prefix') . 'branches')
                ->cascadeOnDelete();

            $table->boolean('is_main')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'branch_company');
    }
};
