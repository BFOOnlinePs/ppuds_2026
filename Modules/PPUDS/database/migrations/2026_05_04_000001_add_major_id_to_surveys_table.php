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
        Schema::table(config('ppuds.table_prefix').'surveys', function (Blueprint $table) {
            $table->foreignId('major_id')
                ->nullable()
                ->after('serve_group')
                ->constrained(config('ppuds.table_prefix').'majors')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('ppuds.table_prefix').'surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('major_id');
        });
    }
};
