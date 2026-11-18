<?php

use App\Models\Member;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor identitas anggota (NIK / NIM / NISN, kolom `nik`) hanya boleh dilihat pemilik akunnya.
 *
 *  - `nik`      : nomor TERENKRIPSI (kunci APP_KEY). Hanya dibuka untuk ditampilkan kepada anggota itu sendiri.
 *  - `nik_hash` : sidik nomor (HMAC-SHA256) untuk cek nomor ganda dan pencarian nomor lengkap di loket,
 *                 tanpa perlu membuka nomornya.
 *
 * PENTING: APP_KEY wajib dicadangkan. Bila APP_KEY hilang atau terganti, nomor yang sudah terenkripsi
 * tidak dapat dibuka lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'nik_hash')) {
                $table->string('nik_hash', 64)->nullable()->after('nik')->index();
            }
            // Hasil enkripsi jauh lebih panjang daripada nomornya
            $table->text('nik')->nullable()->change();
        });

        DB::table('members')->whereNotNull('nik')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $plain = $this->plainValue($row->nik);
                DB::table('members')->where('id', $row->id)->update([
                    'nik'      => $plain === null || $plain === '' ? null : Crypt::encryptString($plain),
                    'nik_hash' => Member::identityHash($plain),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('members')->whereNotNull('nik')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('members')->where('id', $row->id)->update(['nik' => $this->plainValue($row->nik)]);
            }
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('nik', 30)->nullable()->change();
            if (Schema::hasColumn('members', 'nik_hash')) {
                $table->dropIndex(['nik_hash']);
                $table->dropColumn('nik_hash');
            }
        });
    }

    /** Nomor asli dari isi kolom, baik yang sudah terenkripsi maupun yang belum (migrasi aman diulang). */
    private function plainValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
};
