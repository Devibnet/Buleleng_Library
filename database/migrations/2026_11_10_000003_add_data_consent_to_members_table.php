<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti persetujuan pemrosesan data pribadi saat pendaftaran online
     * (UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi).
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'data_consent_at')) {
                $table->timestamp('data_consent_at')->nullable()->after('verification_rejection_reason');
            }
            if (! Schema::hasColumn('members', 'data_consent_version')) {
                $table->string('data_consent_version', 20)->nullable()->after('data_consent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['data_consent_at', 'data_consent_version']);
        });
    }
};
