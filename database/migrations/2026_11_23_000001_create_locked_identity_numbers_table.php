<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor identitas (NIK / NIM / NISN) yang dikunci karena pemiliknya menghapus akun saat masih punya kasus
 * di Keranjang Bermasalah. Data diri sudah dihapus; yang tersisa hanya sidik nomor (bukan nomor asli),
 * nomor tersamar, dan catatan kasus yang belum selesai. Selama kunci aktif, nomor ini tidak bisa dipakai
 * mendaftar lagi sampai petugas membukanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locked_identity_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('nik_hash', 64)->nullable()->index();
            $table->string('number_kind', 10)->default('nik');
            $table->string('masked_number', 32)->nullable();
            $table->string('former_member_no', 50)->nullable();
            $table->json('cases');
            $table->timestamp('locked_at');
            $table->timestamp('unlocked_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('unlocked_by_name')->nullable();
            $table->text('unlock_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locked_identity_numbers');
    }
};
