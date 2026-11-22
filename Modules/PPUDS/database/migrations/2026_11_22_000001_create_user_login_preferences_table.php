<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One person's own sign-in method, set by an administrator. Each column
     * left empty means "follow the role rules, then the general setting".
     * Kept in its own table so the core users table stays untouched.
     */
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'user_login_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // InternalLoginMode, or null to follow the role / general setting.
            $table->unsignedTinyInteger('login_mode')->nullable()->index();

            // OtpChannel, or null to follow the role / general setting.
            $table->unsignedTinyInteger('otp_channel')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'user_login_preferences');
    }
};
