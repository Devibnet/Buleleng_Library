<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminActivityLog;
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

    public function log(
        Request $request,
        ?Admin $actor,
        string $action,
        ?Admin $target,
        array $changes = [],
        ?string $description = null,
        ?array $targetSnapshot = null
    ): void {
        try {
            $snapshot = $targetSnapshot ?? ($target ? self::snapshot($target) : []);

            AdminActivityLog::create([
                'actor_id'        => $actor?->id,
                'actor_name'      => $actor?->name,
                'actor_username'  => $actor?->username,
                'actor_role'      => $actor?->role,
                'action'          => $action,
                'target_id'       => $target?->id,
                'target_name'     => $snapshot['name'] ?? null,
                'target_username' => $snapshot['username'] ?? null,
                'target_role'     => $snapshot['role'] ?? null,
                'changes'         => $changes ?: null,
                'description'     => $description ?? $this->describe($action, $actor, $snapshot, $changes),
                'ip_address'      => $request->ip(),
                'user_agent'      => Str::limit((string) $request->userAgent(), 250, ''),
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

    private function describe(string $action, ?Admin $actor, array $target, array $changes): string
    {
        $who = $actor ? "{$actor->name} ({$actor->username})" : 'Sistem';
        $whom = isset($target['name']) ? "{$target['name']} ({$target['username']})" : 'akun petugas';
        $role = self::roleLabel($target['role'] ?? null);

        return match ($action) {
            'account_created'     => "{$who} membuat akun {$role} baru: {$whom}.",
            'role_changed'        => "{$who} mengubah peran {$whom} dari "
                . self::roleLabel($changes['role']['from'] ?? null) . ' menjadi '
                . self::roleLabel($changes['role']['to'] ?? null) . '.',
            'account_activated'   => "{$who} mengaktifkan akun {$role} {$whom}.",
            'account_deactivated' => "{$who} menonaktifkan akun {$role} {$whom}.",
            'password_changed'    => "{$who} mengganti password akun {$whom}.",
            'account_deleted'     => "{$who} menghapus akun {$role} {$whom}.",
            'reset_link_created'  => "{$who} membuat link reset password untuk akun {$role} {$whom}.",
            'reset_link_revoked'  => "{$who} membatalkan link reset password untuk akun {$role} {$whom}.",
            'password_reset_via_link' => "{$whom} mengganti password melalui link reset password.",
            default               => "{$who} memperbarui data akun {$whom}.",
        };
    }
}
