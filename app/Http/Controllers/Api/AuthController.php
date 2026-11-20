<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Admin;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/auth/member/login
     * Login anggota HANYA dengan Email + kata sandi (No. Anggota dan NIK tidak lagi diterima).
     */
    public function loginMember(Request $request): JsonResponse
    {
        $input = $request->all();
        // 'identifier' tetap diterima demi kompatibilitas klien lama, tetapi isinya wajib email.
        $email = strtolower(trim((string) ($input['email'] ?? $input['identifier'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            return response()->json([
                'success' => false,
                'message' => 'Email dan kata sandi wajib diisi.',
            ], 422);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'Login hanya dapat menggunakan alamat email. Contoh: nama@domain.com',
            ], 422);
        }

        $member = Member::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Akun anggota dengan email tersebut tidak ditemukan.',
            ], 401);
        }

        // Cek validitas password
        $passwordValid = false;

        if (Hash::check($password, $member->password)) {
            $passwordValid = true;
        } elseif ($password === 'atlas123456') {
            // Password default bawaan pendaftaran offline
            $passwordValid = true;
        } elseif (! empty($member->date_of_birth)) {
            // Alternatif password pendaftaran offline: Tanggal Lahir (DDMMYYYY atau DD-MM-YYYY)
            $dobRaw = is_string($member->date_of_birth)
                ? \Carbon\Carbon::parse($member->date_of_birth)
                : $member->date_of_birth;

            $dobFormatted = $dobRaw->format('dmY');       // contoh: 15052000
            $dobFormattedAlt = $dobRaw->format('d-m-Y');  // contoh: 15-05-2000
            $dobFormattedYmd = $dobRaw->format('Ymd');    // contoh: 20000515

            if ($password === $dobFormatted || $password === $dobFormattedAlt || $password === $dobFormattedYmd) {
                $passwordValid = true;
            }
        }

        if (! $passwordValid) {
            return response()->json([
                'success' => false,
                'message' => 'Kata sandi salah. Silakan periksa kembali kata sandi yang Anda masukkan.',
            ], 401);
        }

        Auth::guard('member')->login($member);

        $token = $member->createToken('member-auth-token')->plainTextToken;

        $intendedBookId = session('intended_book_id');
        session()->forget('intended_book_id');

        return response()->json([
            'success'              => true,
            'message'              => 'Login member berhasil.',
            'intended_book_id'     => $intendedBookId,
            'token'                => $token,
            'must_change_password' => (bool) $member->must_change_password,
            'data'                 => [
                'token'                => $token,
                'must_change_password' => (bool) $member->must_change_password,
                'member'               => new MemberResource($member),
            ],
        ], 200);
    }

    /**
     * POST /api/auth/member/force-change-password
     * Wajib ganti kata sandi pertama kali untuk anggota pendaftaran offline
     */
    public function forceChangePassword(Request $request): JsonResponse
    {
        $member = $request->user();

        if (! $member instanceof Member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required'  => 'Kata sandi baru wajib diisi.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $member->update([
            'password'             => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diperbarui. Anda sekarang dapat menggunakan seluruh layanan perpustakaan.',
            'data'    => [
                'member' => new MemberResource($member->fresh()),
            ],
        ], 200);
    }

    /**
     * POST /api/auth/admin/login
     */
    public function loginAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial username/email atau password admin salah.',
            ], 401);
        }

        if (! $admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif.',
            ], 403);
        }

        Auth::guard('admin')->login($admin);

        $token = $admin->createToken('admin-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login admin berhasil.',
            'token'   => $token,
            'data'    => [
                'token' => $token,
                'admin' => [
                    'id'        => $admin->id,
                    'username'  => $admin->username,
                    'name'      => $admin->name,
                    'email'     => $admin->email,
                    'role'      => $admin->role,
                    'is_active' => (bool) $admin->is_active,
                ],
            ],
        ], 200);
    }
}
