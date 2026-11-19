<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per sign-in code sent. The code itself is never stored, only
     * its keyed hash, so a database leak does not hand out working codes.
     */
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'login_otps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('channel');

            // OtpPurpose: a code only works for what it was issued for, so a
            // code-only code can never stand in for the step after a password.
            $table->unsignedTinyInteger('purpose');
            $table->char('code_hash', 64);

            // Set when the code was requested from a company QR code: the code
            // then only signs the person in for that company.
            $table->foreignId('company_id')->nullable()->constrained(config('ppuds.table_prefix') . 'companies')->cascadeOnDelete();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('ip', 45)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'consumed_at', 'expires_at'], 'ppuds_login_otps_active_idx');
            $table->index(['channel', 'created_at'], 'ppuds_login_otps_channel_day_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'login_otps');
    }
};
