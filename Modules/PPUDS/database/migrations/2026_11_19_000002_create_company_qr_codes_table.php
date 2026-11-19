<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A company's sign-in QR code. The QR only names the company — it is not
     * a credential, the person still confirms who they are with a one-time
     * code. The token is looked up by its hash, and kept encrypted so an
     * administrator can download or print the same QR again later.
     */
    public function up(): void
    {
        Schema::create(config('ppuds.table_prefix') . 'company_qr_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained(config('ppuds.table_prefix') . 'companies')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->text('token');

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);

            $table->timestamps();

            $table->index(['company_id', 'revoked_at'], 'ppuds_company_qr_codes_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'company_qr_codes');
    }
};
