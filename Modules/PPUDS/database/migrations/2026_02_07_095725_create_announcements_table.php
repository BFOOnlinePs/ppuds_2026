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
        Schema::create(config('ppuds.table_prefix') . 'announcements', function (Blueprint $table) {
            $table->id();

            $table->json('target_roles')->nullable();
            $table->json('filters')->nullable();

            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();

            $table->boolean('is_pinned')->default(false);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'announcements');
    }
};
