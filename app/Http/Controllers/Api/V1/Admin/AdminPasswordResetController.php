<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminPasswordReset;
use App\Services\AdminActivityLogger;
use App\Support\StaffPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Reset password petugas melalui link sekali pakai yang dibuat Super Admin.
 *
 * Alur:
 * 1. Super Admin membuat link untuk akun Admin/Operator (berlaku 30 menit).
 * 2. Petugas membuka link → token diverifikasi (belum dipakai, belum dicabut, belum kedaluwarsa).
 * 3. Jika valid, petugas mengisi password baru → token ditandai terpakai & seluruh sesi login dicabut.
 */
class AdminPasswordResetController extends Controller
{
    public function __construct(private AdminActivityLogger $activityLogger)
    {
    }

    /**
     * POST /api/v1/admin/access/users/{id}/reset-link  (super_admin)
     */
    public function createLink(Request $request, int $id): JsonResponse
    {
        $actor = Auth::guard('sanctum')->user() ?? $request->user();
        $target = Admin::find($id);

        if (! $target) {
            return response()->json(['success' => false, 'message' => 'Akun petugas tidak ditemukan.'], 404);
        }

        if (! in_array($target->role, ['admin', 'operator'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Link reset password hanya dapat dibuat untuk akun Admin atau Operator.',
            ], 422);
        }

        if (! $target->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini sedang nonaktif. Aktifkan akun terlebih dahulu sebelum membuat link reset password.',
            ], 422);
        }

        $token = Str::random(64);
        $expiresAt = now()->addMinutes(AdminPasswordReset::LIFETIME_MINUTES);

        DB::transaction(function () use ($target, $token, $expiresAt, $actor) {
            // Hanya satu link aktif per akun: cabut link lama yang belum dipakai.
            AdminPasswordReset::where('admin_id', $target->id)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            AdminPasswordReset::create([
                'admin_id'   => $target->id,
                'token_hash' => AdminPasswordReset::hashToken($token),
                'created_by' => $actor?->id,
                'expires_at' => $expiresAt,
            ]);
        });

        $this->activityLogger->log(
            $request,
            $actor instanceof Admin ? $actor : null,
            'reset_link_created',
            $target,
            ['reset_link' => ['from' => null, 'to' => 'Berlaku ' . AdminPasswordReset::LIFETIME_MINUTES . ' menit']]
        );

        return response()->json([
            'success' => true,
            'message' => "Link reset password untuk {$target->name} berhasil dibuat.",
            'data'    => [
                'token'              => $token,
                'expires_at'         => $expiresAt->toIso8601String(),
                'expires_at_formatted' => $expiresAt->copy()->timezone('Asia/Makassar')->format('H:i') . ' WITA',
                'lifetime_minutes'   => AdminPasswordReset::LIFETIME_MINUTES,
                'target'             => [
                    'id'       => $target->id,
                    'name'     => $target->name,
                    'username' => $target->username,
                    'role'     => $target->role,
                ],
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/admin/access/users/{id}/reset-link  (super_admin)
     * Batalkan link reset password yang masih aktif. Link yang dibatalkan langsung tidak valid.
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $actor = Auth::guard('sanctum')->user() ?? $request->user();
        $target = Admin::find($id);

        if (! $target) {
            return response()->json(['success' => false, 'message' => 'Akun petugas tidak ditemukan.'], 404);
        }

        $revoked = AdminPasswordReset::usable()
            ->where('admin_id', $target->id)
            ->update(['revoked_at' => now()]);

        if ($revoked === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada link reset password aktif untuk akun ini. Link mungkin sudah dipakai atau kedaluwarsa.',
            ], 404);
        }

        $this->activityLogger->log(
            $request,
            $actor instanceof Admin ? $actor : null,
            'reset_link_revoked',
            $target,
            ['reset_link' => ['from' => 'Aktif', 'to' => 'Dibatalkan']]
        );

        return response()->json([
            'success' => true,
            'message' => "Link reset password untuk {$target->name} berhasil dibatalkan dan tidak dapat digunakan lagi.",
        ]);
    }

    /**
     * GET /api/v1/admin/access/users/{id}/reset-link  (super_admin)
     * Status link reset aktif milik sebuah akun. Token asli tidak dapat ditampilkan ulang
     * karena hanya hash-nya yang disimpan.
     */
    public function status(int $id): JsonResponse
    {
        $reset = AdminPasswordReset::usable()
            ->where('admin_id', $id)
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $reset ? [
                'active'               => true,
                'expires_at'           => $reset->expires_at->toIso8601String(),
                'expires_at_formatted' => $reset->expires_at->copy()->timezone('Asia/Makassar')->format('H:i') . ' WITA',
                'created_at'           => $reset->created_at?->toIso8601String(),
                'lifetime_minutes'     => AdminPasswordReset::LIFETIME_MINUTES,
            ] : ['active' => false],
        ]);
    }

    /**
     * GET /api/v1/auth/admin/reset-password/{token}  (publik)
     * Cek apakah link masih berlaku sebelum form ganti password ditampilkan.
     */
    public function verify(string $token): JsonResponse
    {
        [$reset, $error] = $this->findUsable($token);
        if ($error) {
            return $error;
        }

        return response()->json([
            'success' => true,
            'message' => 'Link reset password valid.',
            'data'    => [
                'name'              => $reset->admin->name,
                'username'          => $reset->admin->username,
                'role'              => $reset->admin->role,
                'expires_at'        => $reset->expires_at->toIso8601String(),
                'seconds_remaining' => max(0, (int) floor(now()->diffInSeconds($reset->expires_at, false))),
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/admin/reset-password  (publik)
     * Body: token, password, password_confirmation
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'    => ['required', 'string'],
            'password' => StaffPassword::rules(true),
        ], StaffPassword::messages() + [
            'token.required' => 'Token reset password tidak ditemukan.',
        ]);

        [$reset, $error] = $this->findUsable($validated['token']);
        if ($error) {
            return $error;
        }

        $admin = $reset->admin;

        DB::transaction(function () use ($admin, $reset, $validated) {
            $admin->update(['password' => Hash::make($validated['password'])]);
            $reset->update(['used_at' => now()]);
            // Paksa login ulang di semua perangkat dengan password baru.
            $admin->tokens()->delete();
        });

        $this->activityLogger->log(
            $request,
            $admin,
            'password_reset_via_link',
            $admin,
            ['password' => ['from' => null, 'to' => null, 'changed' => true]]
        );

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diganti. Silakan login dengan password baru.',
        ]);
    }

    /**
     * @return array{0: ?AdminPasswordReset, 1: ?JsonResponse}
     */
    private function findUsable(string $token): array
    {
        $reset = strlen($token) === 64
            ? AdminPasswordReset::with('admin')->where('token_hash', AdminPasswordReset::hashToken($token))->first()
            : null;

        if (! $reset) {
            return [null, response()->json([
                'success' => false,
                'reason'  => 'invalid',
                'message' => 'Link reset password tidak valid. Pastikan link disalin dengan lengkap.',
            ], 404)];
        }

        if ($reason = $reset->invalidReason()) {
            return [null, response()->json([
                'success' => false,
                'reason'  => $reset->expires_at->isPast() && ! $reset->used_at && ! $reset->revoked_at ? 'expired' : 'unusable',
                'message' => $reason,
            ], 410)];
        }

        return [$reset, null];
    }
}
