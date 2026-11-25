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
use Illuminate\Support\Facades\Validator;

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

        // Cek status aktivasi akun digital anggota (Proses Bisnis Aktivasi Akun Digital)
        if ($member->activation_status && $member->activation_status !== Member::ACTIVATION_STATUS_AKTIF) {
            $msg = match ($member->activation_status) {
                Member::ACTIVATION_STATUS_GAGAL_DIKIRIM => 'Pengiriman email aktivasi ke akun Anda mengalami kendala. Silakan kunjungi petugas loket perpustakaan untuk memeriksa email dan mengirim ulang email aktivasi.',
                Member::ACTIVATION_STATUS_TERVERIFIKASI, Member::ACTIVATION_STATUS_MENUNGGU_PASSWORD => 'Email Anda telah terverifikasi, namun pembuatan kata sandi belum selesai. Silakan periksa kembali tautan aktivasi di email Anda untuk membuat kata sandi.',
                default => 'Akun digital Anda belum aktif (Menunggu Aktivasi). Silakan periksa kotak masuk atau spam email Anda dan buka tautan aktivasi untuk membuat kata sandi.',
            };
            return response()->json([
                'success'           => false,
                'activation_status' => $member->activation_status,
                'message'           => $msg,
            ], 403);
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
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
            ],
        ], [
            'password.required'  => 'Kata sandi baru wajib diisi.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.regex'     => 'Kata sandi harus memuat kombinasi huruf besar, angka, dan karakter unik/simbol.',
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
     * POST /api/auth/member/verify-activation-token
     * Verifikasi token aktivasi dari email anggota (Proses Bisnis 2.3 & 2.4)
     */
    public function verifyActivationToken(Request $request): JsonResponse
    {
        $token = trim((string) $request->input('token', ''));
        if ($token === '') {
            return response()->json([
                'success' => false,
                'message' => 'Token aktivasi wajib disertakan.',
            ], 422);
        }

        $tokenHash = Member::hashActivationToken($token);
        $member = Member::where('activation_token_hash', $tokenHash)->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Tautan aktivasi tidak valid atau sudah pernah digunakan sebelumnya.',
            ], 404);
        }

        if ($member->activation_status === Member::ACTIVATION_STATUS_AKTIF) {
            return response()->json([
                'success'        => false,
                'already_active' => true,
                'message'        => 'Akun digital Anda sudah aktif. Silakan langsung masuk ke sistem menggunakan email dan kata sandi Anda.',
            ], 400);
        }

        if ($member->activation_expires_at && $member->activation_expires_at->isPast()) {
            return response()->json([
                'success'    => false,
                'is_expired' => true,
                'message'    => 'Tautan aktivasi ini telah kedaluwarsa (batas waktu 24 jam). Silakan hubungi petugas loket perpustakaan untuk meminta pengiriman ulang tautan aktivasi.',
            ], 410);
        }

        // Ubah status menjadi Terverifikasi / Menunggu Password sesuai ketentuan
        if ($member->activation_status !== Member::ACTIVATION_STATUS_MENUNGGU_PASSWORD) {
            $member->update([
                'activation_status'      => Member::ACTIVATION_STATUS_MENUNGGU_PASSWORD,
                'activation_verified_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email berhasil diverifikasi. Silakan buat kata sandi baru untuk mengaktifkan akun digital Anda.',
            'data'    => [
                'name'        => $member->name,
                'member_no'   => $member->member_no,
                'email'       => $member->email,
                'verified_at' => $member->activation_verified_at,
            ],
        ]);
    }

    /**
     * POST /api/auth/member/complete-activation
     * Pembuatan kata sandi mandiri oleh anggota untuk mengaktifkan akun (Proses Bisnis 2.6 - 2.8)
     */
    public function completeActivation(Request $request): JsonResponse
    {
        $token = trim((string) $request->input('token', ''));
        if ($token === '') {
            return response()->json([
                'success' => false,
                'message' => 'Token aktivasi wajib disertakan.',
            ], 422);
        }

        $tokenHash = Member::hashActivationToken($token);
        $member = Member::where('activation_token_hash', $tokenHash)->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Tautan aktivasi tidak valid atau sudah kedaluwarsa.',
            ], 404);
        }

        if ($member->activation_status === Member::ACTIVATION_STATUS_AKTIF) {
            return response()->json([
                'success'        => false,
                'already_active' => true,
                'message'        => 'Akun digital Anda sudah aktif. Silakan langsung masuk menggunakan email dan kata sandi Anda.',
            ], 400);
        }

        if ($member->activation_expires_at && $member->activation_expires_at->isPast()) {
            return response()->json([
                'success'    => false,
                'is_expired' => true,
                'message'    => 'Tautan aktivasi ini telah kedaluwarsa. Silakan hubungi petugas loket untuk mengirim ulang tautan aktivasi.',
            ], 410);
        }

        $validator = Validator::make($request->all(), [
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
            ],
        ], [
            'password.required'  => 'Kata sandi baru wajib diisi.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.regex'     => 'Kata sandi harus memuat kombinasi huruf besar, angka, dan karakter unik/simbol.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Simpan password dalam bentuk hash, ubah status menjadi AKTIF, dan batalkan token aktivasi (one-time use)
        $member->update([
            'password'                 => Hash::make($request->password),
            'activation_status'        => Member::ACTIVATION_STATUS_AKTIF,
            'activation_token_hash'    => null,
            'activation_expires_at'    => null,
            'must_change_password'     => false,
            'password_changed_at'      => now(),
            'activation_failed_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun digital Anda berhasil diaktifkan. Silakan masuk menggunakan email dan kata sandi baru Anda.',
            'data'    => [
                'name'      => $member->name,
                'email'     => $member->email,
                'member_no' => $member->member_no,
            ],
        ]);
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
