<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alur verifikasi identitas tanpa menyimpan foto KTP:
     *  - identity_verified_via     : 'mesin_online' (NIK dicocokkan dengan foto di browser pendaftar,
     *                                 foto tidak dikirim) atau 'tatap_muka' (petugas memeriksa KTP asli).
     *  - verification_expires_at   : batas waktu pendaftar datang ke DAPD untuk verifikasi tatap muka;
     *                                 lewat batas ini data pendaftaran dihapus otomatis.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'identity_verified_via')) {
                $table->string('identity_verified_via', 20)->nullable()->after('identity_ocr_result');
            }
            if (! Schema::hasColumn('members', 'verification_expires_at')) {
                $table->timestamp('verification_expires_at')->nullable()->after('identity_verified_via');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['identity_verified_via', 'verification_expires_at']);
        });
    }
};
