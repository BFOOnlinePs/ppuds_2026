<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\SymbolPosition;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();

            $table->string('code', 3)->unique();
            $table->string('symbol', 10);
            $table->string('symbol_position', 10)->default(SymbolPosition::BEFORE->value());
            $table->integer('decimal_places')->default(2);
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->references('id')->on('users');

            $table->softDeletes();
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
