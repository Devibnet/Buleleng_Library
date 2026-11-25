<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /** Kunci cache tanda "sudah masuk ulang dengan Google" untuk hapus akun, diikuti id anggota. */
    public const DELETE_REAUTH_CACHE = 'account-delete-google:';

    private const DELETE_REAUTH_PURPOSE = 'delete-account';

    /**
     * Alamat masuk ulang Google sebelum hapus akun (POST /api/v1/profile/google-reauth, perlu login).
     *
     * Akun Google tidak punya kata sandi yang diketahui pemiliknya, jadi bukti pemilik akun adalah masuk
     * ulang dengan akun Google yang sama. Parameter `state` berisi id anggota terenkripsi dan berlaku
     * 10 menit; callback memeriksa bahwa akun Google yang dipilih memang yang terhubung ke anggota ini.
     */
    public function deleteAccountRedirect(Request $request): JsonResponse
    {
        $member = $request->user();
        if (! $member instanceof Member || empty($member->google_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini tidak terhubung dengan Google. Konfirmasi hapus akun memakai kata sandi.',
            ], 422);
        }

        // Hambatan dicek sebelum ke Google, supaya pendaftar tidak bolak-balik untuk hasil yang pasti ditolak
        if ($blocked = ProfileController::accountDeletionBlockReason($member)) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        $state = Crypt::encryptString(json_encode([
            'purpose' => self::DELETE_REAUTH_PURPOSE,
            'member'  => $member->id,
            'expires' => now()->addMinutes(10)->timestamp,
        ]));

        $url = Socialite::driver('google')
            ->stateless()
            ->with(['prompt' => 'select_account', 'login_hint' => $member->email, 'state' => $state])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['success' => true, 'url' => $url]);
    }

    /** Isi `state` masuk ulang hapus akun, atau null bila ini login biasa / state tidak sah. */
    private function deleteReauthState(): ?array
    {
        $raw = (string) request('state', '');
        if ($raw === '') {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString($raw), true);
        } catch (Throwable) {
            return null;
        }

        return is_array($data) && ($data['purpose'] ?? null) === self::DELETE_REAUTH_PURPOSE ? $data : null;
    }

    /** Selesaikan masuk ulang hapus akun lalu kembali ke halaman profil. */
    private function finishDeleteReauth(array $state, string $frontendUrl): RedirectResponse
    {
        $back = fn (string $query) => redirect("{$frontendUrl}/profil?{$query}");
        $fail = fn (string $message) => $back('hapus_akun_galat=' . urlencode($message));

        if (request()->has('error')) {
            return $fail('Masuk ulang dengan Google dibatalkan. Akun tidak dihapus.');
        }
        if (($state['expires'] ?? 0) < now()->timestamp) {
            return $fail('Waktu konfirmasi sudah habis. Coba hapus akun lagi.');
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Google re-auth (hapus akun) gagal: ' . $e->getMessage());

            return $fail('Masuk ulang dengan Google gagal. Coba lagi.');
        }

        $member = Member::find($state['member'] ?? 0);
        if (! $member || empty($member->google_id) || ! hash_equals((string) $member->google_id, (string) $googleUser->getId())) {
            return $fail('Akun Google yang dipilih bukan akun yang terhubung dengan keanggotaan ini. Akun tidak dihapus.');
        }

        Cache::put(self::DELETE_REAUTH_CACHE . $member->id, true, now()->addMinutes(10));

        return $back('hapus_akun=google_ok');
    }

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

        if ($reauth = $this->deleteReauthState()) {
            return $this->finishDeleteReauth($reauth, $frontendUrl);
        }

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
