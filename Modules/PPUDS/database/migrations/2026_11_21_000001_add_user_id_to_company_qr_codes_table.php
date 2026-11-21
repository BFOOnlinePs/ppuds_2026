<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A QR code can now belong to one person as well as to a company.
     *
     * Without a user it is the company's printed code: it only names the
     * company, and the person still confirms who they are with a one-time
     * code. With a user it is that person's own sign-in code: scanning it
     * signs them straight in, so it is handed to them privately.
     */
    public function up(): void
    {
        $table = config('ppuds.table_prefix') . 'company_qr_codes';

        if (! Schema::hasColumn($table, 'user_id')) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('company_id')->constrained('users')->cascadeOnDelete();
                $table->index(['company_id', 'user_id', 'revoked_at'], 'ppuds_company_qr_codes_personal_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('ppuds.table_prefix') . 'company_qr_codes';

        if (Schema::hasColumn($table, 'user_id')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex('ppuds_company_qr_codes_personal_idx');
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
