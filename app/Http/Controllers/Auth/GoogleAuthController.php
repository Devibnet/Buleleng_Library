<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(): RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')
            ->stateless()
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Obtain the user information from Google and authenticate.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

        if (request()->has('error')) {
            $error = request('error');
            return redirect("{$frontendUrl}/login?error=" . urlencode("Autentikasi Google dibatalkan atau ditolak ({$error})."));
        }

        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            try {
                $googleUser = Socialite::driver('google')->stateless()->user();
            } catch (Throwable $statelessErr) {
                $googleUser = Socialite::driver('google')->user();
            }
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Google OAuth Callback Error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $errorMsg = $e->getMessage() ?: class_basename($e);
            return redirect("{$frontendUrl}/login?error=" . urlencode('Gagal melakukan autentikasi dengan Google: ' . $errorMsg));
        }

        // Cari record di tabel members berdasarkan google_id atau email
        $member = Member::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        $isNewMember = false;

        if (! $member) {
            $isNewMember = true;
            // Buat member baru di database dari akun Google
            $member = Member::create([
                'member_no'   => Member::generateMemberNo(),
                'name'        => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Pengguna Google',
                'email'       => $googleUser->getEmail(),
                'google_id'   => $googleUser->getId(),
                'avatar'      => $googleUser->getAvatar(),
                'photo_path'  => $googleUser->getAvatar(),
                'password'    => Hash::make(Str::random(24)),
            ]);
        } else {
            // Update data google jika sebelumnya masih kosong
            $needsUpdate = false;

            if (empty($member->google_id)) {
                $member->google_id = $googleUser->getId();
                $needsUpdate = true;
            }

            if (empty($member->photo_path)) {
                $member->photo_path = $googleUser->getAvatar();
                $needsUpdate = true;
            }

            if (empty($member->avatar)) {
                $member->avatar = $googleUser->getAvatar();
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $member->save();
            }
        }

        // Login menggunakan guard member
        Auth::guard('member')->login($member);

        // Buat token autentikasi Sanctum untuk SPA
        $token = $member->createToken('google-auth-token')->plainTextToken;

        // Cek kelengkapan data diri:
        // Member baru belum melengkapi data diri.
        // Member yang sudah ada dinyatakan lengkap jika telah mengisi nomor handphone dan (alamat KTP/domisili atau kategori pekerjaan).
        $hasCompletedData = ! $isNewMember
            && ! empty($member->phone_number)
            && (! empty($member->ktp_address) || ! empty($member->residential_address) || ! empty($member->job_category));

        $userData = json_encode([
            'id'                 => $member->id,
            'name'               => $member->name,
            'email'              => $member->email,
            'member_no'          => $member->member_no,
            'avatar'             => $member->avatar ?? $member->photo_path,
            'phone_number'       => $member->phone_number,
            'job_category'       => $member->job_category,
            'has_completed_data' => $hasCompletedData,
        ]);

        $targetPath = $hasCompletedData ? '/' : '/form-data-diri';

        return redirect("{$frontendUrl}{$targetPath}?token=" . urlencode($token) . "&user=" . urlencode($userData));
    }
}
