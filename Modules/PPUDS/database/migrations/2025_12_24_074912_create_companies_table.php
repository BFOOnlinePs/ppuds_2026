<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\PPUDS\Enums\Enums\CompanyStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'companies', function (Blueprint $table) {
            $table->id();

            $table->string('website')->nullable();
            $table->foreignId('company_category_id')->index('company_category_id')->constrained(config('ppuds.table_prefix') . 'company_categories')->cascadeOnDelete();
            $table->integer('status')->default(CompanyStatus::ACTIVE->value);
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
        Schema::dropIfExists(config('ppuds.table_prefix') . 'companies');
    }
};
