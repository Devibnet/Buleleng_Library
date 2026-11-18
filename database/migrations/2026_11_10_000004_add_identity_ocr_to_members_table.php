<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jenis dokumen identitas yang diunggah saat pendaftaran online, beserta hasil
     * pencocokan otomatis (OCR) antara NIK di foto dengan NIK yang diketik pendaftar.
     * Hasil OCR hanya bahan bantu bagi petugas verifikasi.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'identity_document_type')) {
                $table->string('identity_document_type', 20)->nullable()->after('data_consent_version');
            }
            if (! Schema::hasColumn('members', 'identity_ocr_status')) {
                $table->string('identity_ocr_status', 20)->nullable()->after('identity_document_type');
            }
            if (! Schema::hasColumn('members', 'identity_ocr_result')) {
                $table->json('identity_ocr_result')->nullable()->after('identity_ocr_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['identity_document_type', 'identity_ocr_status', 'identity_ocr_result']);
        });
    }
};
