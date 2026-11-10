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
     * Mendukung login dengan Email, Nomor Anggota (member_no), atau NIK
     */
    public function loginMember(Request $request): JsonResponse
    {
        $input = $request->all();
        $identifier = trim($input['identifier'] ?? $input['email'] ?? $input['username'] ?? '');
        $password = (string) ($input['password'] ?? '');

        if (empty($identifier) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'No. Anggota / NIK / Email dan kata sandi wajib diisi.',
            ], 422);
        }

        // Cari member berdasarkan email, nomor anggota, atau NIK
        $member = Member::where(function ($q) use ($identifier) {
            $q->where('email', $identifier)
              ->orWhere('member_no', $identifier)
              ->orWhere('nik', $identifier);
        })->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Akun anggota tidak ditemukan. Pastikan No. Anggota, NIK, atau Email yang dimasukkan benar.',
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
                'message' => 'Kata sandi salah. Untuk pendaftaran offline di loket, gunakan kata sandi awal atlas123456 atau tanggal lahir Anda (format DDMMYYYY).',
            ], 401);
        }

        Auth::guard('member')->login($member);

        $token = $member->createToken('member-auth-token')->plainTextToken;

        $intendedBookId = session('intended_book_id');
        session()->forget('intended_book_id');

        return response()->json([
            'success'          => true,
            'message'          => 'Login member berhasil.',
            'intended_book_id' => $intendedBookId,
            'token'            => $token,
            'data'             => [
                'token'  => $token,
                'member' => new MemberResource($member),
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
