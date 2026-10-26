<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminProfileController extends Controller
{
    /**
     * Dapatkan instance model Admin yang sedang login via sanctum atau guard admin.
     */
    protected function getAuthenticatedAdmin(Request $request): ?Admin
    {
        $user = Auth::guard('admin')->user() ?? Auth::guard('sanctum')->user() ?? $request->user();

        if ($user instanceof Admin) {
            return $user;
        }

        // Jika auth sanctum me-return user generik atau via ID
        if ($user && isset($user->id)) {
            $admin = Admin::find($user->id);
            if ($admin) {
                return $admin;
            }
        }

        return null;
    }

    /**
     * Format response profil admin.
     */
    protected function formatAdminData(Admin $admin): array
    {
        return [
            'id'         => $admin->id,
            'username'   => $admin->username,
            'name'       => $admin->name,
            'email'      => $admin->email,
            'role'       => $admin->role ?? 'administrator',
            'is_active'  => (bool) $admin->is_active,
            'created_at' => $admin->created_at ? $admin->created_at->toIso8601String() : null,
            'updated_at' => $admin->updated_at ? $admin->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * GET /api/v1/admin/profile
     * Ambil data detail profil admin yang sedang login.
     */
    public function show(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedAdmin($request);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated atau sesi admin telah kedaluwarsa.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data profil admin berhasil diambil.',
            'data'    => $this->formatAdminData($admin),
        ], 200);
    }

    /**
     * PUT /api/v1/admin/profile
     * Perbarui data profil admin: nama, username, email.
     */
    public function update(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedAdmin($request);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated atau sesi admin telah kedaluwarsa.',
            ], 401);
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('admins', 'username')->ignore($admin->id),
            ],
            'email'    => [
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($admin->id),
            ],
        ], [
            'username.required' => 'Username admin wajib diisi.',
            'username.min'      => 'Username minimal 3 karakter.',
            'username.regex'    => 'Username hanya boleh memuat huruf, angka, titik, strip (-), dan garis bawah (_).',
            'username.unique'   => 'Username tersebut telah digunakan oleh admin lain.',
            'email.required'    => 'Alamat email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'email.unique'      => 'Alamat email tersebut telah digunakan oleh admin lain.',
        ]);

        $admin->update($validated);
        $freshAdmin = $admin->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Profil dan username admin berhasil diperbarui.',
            'data'    => $this->formatAdminData($freshAdmin),
        ], 200);
    }

    /**
     * PUT /api/v1/admin/profile/password
     * Perbarui kata sandi admin (ganti password).
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedAdmin($request);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated atau sesi admin telah kedaluwarsa.',
            ], 401);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
                'confirmed',
            ],
        ], [
            'current_password.required' => 'Password lama wajib diisi.',
            'password.required'         => 'Password baru wajib diisi.',
            'password.min'              => 'Password baru minimal 8 karakter.',
            'password.regex'            => 'Password baru harus memuat minimal 1 huruf besar, 1 angka, dan 1 karakter unik/simbol.',
            'password.confirmed'        => 'Konfirmasi password baru tidak cocok.',
        ]);

        if (! Hash::check($validated['current_password'], $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini (lama) tidak sesuai.',
                'errors'  => [
                    'current_password' => ['Password saat ini (lama) tidak sesuai.'],
                ],
            ], 422);
        }

        $admin->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password admin berhasil diubah.',
        ], 200);
    }
}
