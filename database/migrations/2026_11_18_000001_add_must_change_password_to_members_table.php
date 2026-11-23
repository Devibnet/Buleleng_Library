<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wajib ganti kata sandi untuk anggota yang dibuat petugas (kata sandi awal dibuat sistem).
     *  - must_change_password: true saat petugas menambah anggota; menjadi false setelah anggota mengganti
     *    kata sandinya (lewat ForceChangePasswordModal atau halaman profil).
     *  - password_changed_at: kapan kata sandi terakhir diganti oleh anggota sendiri.
     * Anggota yang sudah ada mendapat false, jadi tidak tiba-tiba diminta mengganti kata sandi.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
            if (! Schema::hasColumn('members', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['must_change_password', 'password_changed_at'],
                fn ($column) => Schema::hasColumn('members', $column)
            ));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
