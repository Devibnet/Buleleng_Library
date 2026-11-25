<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\ImpersonationSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pencatat audit trail perubahan akun & hak akses petugas.
 * Pencatatan tidak boleh menggagalkan aksi utama, sehingga error hanya dilaporkan ke log aplikasi.
 */
class AdminActivityLogger
{
    /** Field akun yang dipantau perubahannya. */
    public const TRACKED_FIELDS = ['name', 'username', 'email', 'role', 'is_active'];

    public const ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'admin'       => 'Pustakawan / Admin',
        'operator'    => 'Operator Sirkulasi',
    ];

    /** Snapshot field yang dipantau dari sebuah akun. */
    public static function snapshot(Admin $admin): array
    {
        return [
            'name'      => $admin->name,
            'username'  => $admin->username,
            'email'     => $admin->email,
            'role'      => $admin->role,
            'is_active' => (bool) $admin->is_active,
        ];
    }

    /** Selisih dua snapshot dalam bentuk { field: { from, to } }. */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach (self::TRACKED_FIELDS as $field) {
            $from = $before[$field] ?? null;
            $to = $after[$field] ?? null;
            if ($from !== $to) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    public static function roleLabel(?string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ($role ? ucfirst($role) : '-');
    }

    /**
     * Deteksi apakah request saat ini berjalan di bawah sesi impersonasi aktif.
     */
    public static function resolveActiveImpersonation(Request $request): ?ImpersonationSession
    {
        try {
            // 1. Cek dari header khusus X-Impersonation-Session
            $headerSessionId = $request->header('X-Impersonation-Session');
            if ($headerSessionId) {
                $session = ImpersonationSession::where('session_id', $headerSessionId)->active()->first();
                if ($session) {
                    return $session;
                }
            }

            // 2. Cek dari Bearer token ID (format Sanctum: "{token_id}|{hash}")
            if ($bearer = $request->bearerToken()) {
                if (str_contains($bearer, '|')) {
                    [$tokenId] = explode('|', $bearer, 2);
                    $session = ImpersonationSession::where('token_id', $tokenId)->active()->first();
                    if ($session) {
                        return $session;
                    }
                }
            }

            // 3. Cek dari currentAccessToken model user
            $user = $request->user();
            if ($user && method_exists($user, 'currentAccessToken')) {
                $accessToken = $user->currentAccessToken();
                if ($accessToken) {
                    if (str_starts_with($accessToken->name ?? '', 'impersonation:')) {
                        $parts = explode(':', $accessToken->name);
                        $sessionId = $parts[2] ?? null;
                        if ($sessionId) {
                            $session = ImpersonationSession::where('session_id', $sessionId)->active()->first();
                            if ($session) {
                                return $session;
                            }
                        }
                    }

                    $session = ImpersonationSession::where('token_id', $accessToken->id)->active()->first();
                    if ($session) {
                        return $session;
                    }
                }
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function log(
        Request $request,
        ?Admin $actor,
        string $action,
        mixed $target = null,
        array $changes = [],
        ?string $description = null,
        ?array $targetSnapshot = null,
        ?string $module = 'Manajemen Petugas & Akses',
        string $result = 'success'
    ): void {
        try {
            $impersonation = self::resolveActiveImpersonation($request);

            // Jika sedang dalam sesi impersonasi, identitas pelaku sebenarnya adalah Super Admin
            $actorId = $actor?->id;
            $actorName = $actor?->name;
            $actorUsername = $actor?->username;
            $actorRole = $actor?->role;

            $impersonatorId = null;
            $impersonatorName = null;
            $sessionId = null;
            $reason = null;

            if ($impersonation) {
                $impersonatorId = $impersonation->super_admin_id;
                $impersonatorName = $impersonation->super_admin_name;
                $sessionId = $impersonation->session_id;
                $reason = $impersonation->reason;

                // Pelaku sebenarnya adalah Super Admin
                $actorId = $impersonation->super_admin_id;
                $actorName = $impersonation->super_admin_name;
                $actorUsername = $impersonation->super_admin_username;
                $actorRole = 'super_admin';
            } elseif ($action === 'impersonate_start' || $action === 'impersonate_stop') {
                $sessionId = $changes['session_id'] ?? null;
                $reason = $changes['reason'] ?? null;
                $impersonatorId = $actor?->id;
                $impersonatorName = $actor?->name;
            }

            $targetId = null;
            $targetName = null;
            $targetUsername = null;
            $targetRole = null;

            if ($target instanceof Admin) {
                $snapshot = $targetSnapshot ?? self::snapshot($target);
                $targetId = $target->id;
                $targetName = $snapshot['name'] ?? null;
                $targetUsername = $snapshot['username'] ?? null;
                $targetRole = $snapshot['role'] ?? null;
            } elseif ($target instanceof \App\Models\Member) {
                $snapshot = $targetSnapshot ?? [
                    'name'     => $target->name,
                    'username' => $target->member_no,
                    'role'     => 'Anggota',
                ];
                $targetId = $target->id;
                $targetName = $target->name;
                $targetUsername = $target->member_no;
                $targetRole = 'Anggota';
            } else {
                $snapshot = $targetSnapshot ?? [];
                $targetId = $snapshot['id'] ?? ($snapshot['member_id'] ?? null);
                $targetName = $snapshot['name'] ?? ($snapshot['member_name'] ?? null);
                $targetUsername = $snapshot['username'] ?? ($snapshot['member_no'] ?? null);
                $targetRole = $snapshot['role'] ?? null;
            }

            AdminActivityLog::create([
                'actor_id'                => $actorId,
                'actor_name'              => $actorName,
                'actor_username'          => $actorUsername,
                'actor_role'              => $actorRole,
                'impersonator_id'         => $impersonatorId,
                'impersonator_name'       => $impersonatorName,
                'impersonated_session_id' => $sessionId,
                'impersonation_reason'    => $reason,
                'action'                  => $action,
                'module'                  => $module,
                'target_id'               => $targetId,
                'target_name'             => $targetName,
                'target_username'         => $targetUsername,
                'target_role'             => $targetRole,
                'changes'                 => $changes ?: null,
                'description'             => $description ?? $this->describe($action, $actor, $snapshot, $changes, $impersonation),
                'ip_address'              => $request->ip(),
                'user_agent'              => Str::limit((string) $request->userAgent(), 250, ''),
                'result'                  => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log aktivitas admin: ' . $e->getMessage());
        }
    }

    /** Menentukan jenis aktivitas utama dari sebuah pembaruan akun. */
    public static function resolveUpdateAction(array $changes): ?string
    {
        if (isset($changes['role'])) {
            return 'role_changed';
        }
        if (isset($changes['is_active'])) {
            return $changes['is_active']['to'] ? 'account_activated' : 'account_deactivated';
        }
        if (array_keys($changes) === ['password']) {
            return 'password_changed';
        }

        return $changes ? 'account_updated' : null;
    }

    private function describe(string $action, ?Admin $actor, array $target, array $changes, ?ImpersonationSession $impersonation = null): string
    {
        $who = $actor ? "{$actor->name} ({$actor->username})" : 'Sistem';
        if ($impersonation && $action !== 'impersonate_start' && $action !== 'impersonate_stop') {
            $who = "Super Admin {$impersonation->super_admin_name} (sebagai " . self::roleLabel($impersonation->target_role) . " {$impersonation->target_name})";
        }
        $whom = isset($target['name']) ? "{$target['name']}" . (isset($target['username']) ? " ({$target['username']})" : '') : 'anggota/petugas';
        $role = self::roleLabel($target['role'] ?? null);

        $getVal = fn ($field, $fallback = '-') => is_array($changes[$field] ?? null) ? ($changes[$field]['to'] ?? $fallback) : ($changes[$field] ?? $fallback);

        return match ($action) {
            'account_created'          => "{$who} membuat akun {$role} baru: {$whom}.",
            'role_changed'             => "{$who} mengubah peran {$whom} dari "
                . self::roleLabel($changes['role']['from'] ?? null) . ' menjadi '
                . self::roleLabel($changes['role']['to'] ?? null) . '.',
            'account_activated'        => "{$who} mengaktifkan akun {$role} {$whom}.",
            'account_deactivated'      => "{$who} menonaktifkan akun {$role} {$whom}.",
            'password_changed'         => "{$who} mengganti password akun {$whom}.",
            'account_deleted'          => "{$who} menghapus akun {$role} {$whom}.",
            'reset_link_created'       => "{$who} membuat link reset password untuk akun {$role} {$whom}.",
            'reset_link_revoked'       => "{$who} membatalkan link reset password untuk akun {$role} {$whom}.",
            'password_reset_via_link'  => "{$whom} mengganti password melalui link reset password.",
            'impersonate_start'        => "{$who} memulai sesi impersonasi akun {$role} {$whom}. Alasan: " . ($changes['reason'] ?? '-'),
            'impersonate_stop'         => "{$who} mengakhiri sesi impersonasi akun {$role} {$whom}.",
            'problem_cart_created'     => "{$who} mencatat kasus permasalahan anggota {$whom}: {$getVal('problem_type', 'Masalah')} - {$getVal('item_title', 'Koleksi')}.",
            'problem_cart_updated'     => "{$who} memperbarui kasus permasalahan anggota {$whom}.",
            'problem_cart_followup'    => "{$who} mencatat tindak lanjut kasus anggota {$whom}.",
            'problem_cart_resolved'    => "{$who} menyelesaikan kasus permasalahan anggota {$whom}.",
            'problem_cart_deleted'     => "{$who} menghapus kasus permasalahan anggota {$whom} dari keranjang bermasalah.",
            'problem_cart_bulk_action' => "{$who} menjalankan aksi massal '{$getVal('action', 'aksi')}' pada {$getVal('affected_count', 0)} kasus keranjang bermasalah.",
            // Keanggotaan
            'member_updated'           => "{$who} memperbarui data anggota {$whom}.",
            'member_deleted'           => "{$who} menghapus anggota {$whom}.",
            'member_bulk_deleted'      => "{$who} menghapus massal {$getVal('deleted_count', 0)} anggota.",
            'member_approved'          => "{$who} menyetujui verifikasi identitas anggota {$whom}.",
            'member_rejected'          => "{$who} menolak pendaftaran anggota {$whom}. Alasan: {$getVal('reason', '-')}",
            // Sirkulasi
            'loan_borrowed'            => "{$who} memproses peminjaman {$getVal('loan_count', 1)} buku untuk anggota {$whom}.",
            'loan_returned'            => "{$who} memproses pengembalian {$getVal('returned_count', 1)} buku dari anggota {$whom}.",
            'loan_renewed'             => "{$who} memperpanjang peminjaman buku untuk anggota {$whom}.",
            'reservation_ready'        => "{$who} memverifikasi buku reservasi {$getVal('code', '')} siap diambil oleh {$whom}.",
            'reservation_unavailable'  => "{$who} menandai buku reservasi {$getVal('code', '')} tidak tersedia di rak.",
            'reservation_picked_up'    => "{$who} menyerahkan buku reservasi {$getVal('code', '')} kepada {$whom}.",
            // Master Buku & Koleksi
            'book_created'             => "{$who} mendaftarkan judul buku baru: {$getVal('title', $whom)}.",
            'book_updated'             => "{$who} memperbarui katalog buku {$whom}.",
            'book_deleted'             => "{$who} menghapus katalog buku {$whom}.",
            'collection_updated'       => "{$who} memperbarui eksemplar buku {$whom}.",
            'collection_deleted'       => "{$who} menghapus eksemplar buku {$whom}.",
            // Karantina Koleksi
            'quarantine_moved'         => "{$who} memindahkan koleksi buku ke karantina.",
            'quarantine_restored'      => "{$who} memulihkan koleksi buku dari karantina.",
            'quarantine_bulk_restored' => "{$who} memulihkan koleksi dari karantina secara massal.",
            'quarantine_bulk_deleted'  => "{$who} menghapus koleksi karantina secara permanen.",
            // Loker Penitipan
            'locker_borrowed'          => "{$who} memproses peminjaman loker #{$getVal('locker_number', '-')} untuk {$whom}.",
            'locker_returned'          => "{$who} menerima pengembalian kunci loker #{$getVal('locker_number', '-')} dari {$whom}.",
            default                    => "{$who} melakukan aktivitas {$action} pada {$whom}.",
        };
    }
}
