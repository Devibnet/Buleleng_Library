<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_activity_logs';

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_username',
        'actor_role',
        'impersonator_id',
        'impersonator_name',
        'impersonated_session_id',
        'impersonation_reason',
        'action',
        'module',
        'target_id',
        'target_name',
        'target_username',
        'target_role',
        'changes',
        'description',
        'ip_address',
        'user_agent',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'changes'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** Label aktivitas yang ditampilkan di UI. */
    public const ACTION_LABELS = [
        // 1. Petugas & Hak Akses
        'account_created'          => 'Akun Dibuat',
        'account_updated'          => 'Data Akun Diubah',
        'role_changed'             => 'Peran Diubah',
        'password_changed'         => 'Password Diganti',
        'account_activated'        => 'Akun Diaktifkan',
        'account_deactivated'      => 'Akun Dinonaktifkan',
        'account_deleted'          => 'Akun Dihapus',
        'reset_link_created'       => 'Link Reset Password Dibuat',
        'reset_link_revoked'       => 'Link Reset Password Dibatalkan',
        'password_reset_via_link'  => 'Password Direset via Link',
        'impersonate_start'        => 'Mulai Impersonasi',
        'impersonate_stop'         => 'Selesai Impersonasi',

        // 2. Keanggotaan
        'MEMBER_REGISTRATION_OFFLINE'    => 'Pendaftaran Anggota (Offline)',
        'MEMBER_ACTIVATION_RESEND'       => 'Kirim Ulang Aktivasi Email',
        'MEMBER_EMAIL_CHANGE_ACTIVATION' => 'Ubah Email & Aktivasi',
        'member_updated'                 => 'Data Anggota Diperbarui',
        'member_deleted'                 => 'Anggota Dihapus',
        'member_bulk_deleted'            => 'Hapus Massal Anggota',
        'member_approved'                => 'Verifikasi Anggota Disetujui',
        'member_rejected'                => 'Verifikasi Anggota Ditolak',

        // 3. Keranjang Bermasalah
        'problem_cart_created'     => 'Kasus Masalah Dicatat',
        'problem_cart_updated'     => 'Kasus Masalah Diperbarui',
        'problem_cart_followup'    => 'Tindak Lanjut Kasus',
        'problem_cart_resolved'    => 'Kasus Masalah Selesai',
        'problem_cart_deleted'     => 'Kasus Masalah Dihapus',
        'problem_cart_bulk_action' => 'Aksi Massal Kasus',

        // 4. Layanan Sirkulasi
        'loan_borrowed'            => 'Peminjaman Buku di Loket',
        'loan_returned'            => 'Pengembalian Buku di Loket',
        'loan_renewed'             => 'Perpanjangan Peminjaman Buku',
        'reservation_ready'        => 'Buku Reservasi Siap Diambil',
        'reservation_unavailable'  => 'Buku Reservasi Tidak Tersedia',
        'reservation_picked_up'    => 'Pengambilan Buku Reservasi',

        // 5. Master Buku & Koleksi
        'book_created'             => 'Pendaftaran Buku Baru',
        'book_updated'             => 'Katalog Buku Diperbarui',
        'book_deleted'             => 'Katalog Buku Dihapus',
        'collection_updated'       => 'Eksemplar Buku Diperbarui',
        'collection_deleted'       => 'Eksemplar Buku Dihapus',

        // 6. Karantina Koleksi
        'quarantine_moved'         => 'Pindah ke Karantina',
        'quarantine_restored'      => 'Koleksi Dipulihkan dari Karantina',
        'quarantine_bulk_restored' => 'Pulihkan Massal Karantina',
        'quarantine_bulk_deleted'  => 'Hapus Massal Karantina',

        // 7. Loker Penitipan
        'locker_borrowed'          => 'Peminjaman Loker',
        'locker_returned'          => 'Pengembalian Kunci Loker',
    ];
}
