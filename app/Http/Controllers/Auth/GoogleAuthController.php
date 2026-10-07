<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(): RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google and authenticate.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect('/')
                ->with('error', 'Gagal melakukan autentikasi dengan Google: ' . $e->getMessage());
        }

        // Cari record di tabel members berdasarkan google_id atau email
        $member = Member::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($member) {
            // Jika member sudah ada: update google_id dan photo_path jika sebelumnya masih kosong
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

            // Autentikasi / Login pengguna menggunakan guard member
            Auth::guard('member')->login($member);

            // Cek apakah ada intent reservasi tertunda pasca login
            $intendedBookId = session('intended_book_id');
            if ($intendedBookId) {
                session()->forget('intended_book_id');
                return redirect("/books/{$intendedBookId}")
                    ->with('info', 'Anda telah berhasil login. Silakan lanjutkan reservasi buku.');
            }

            return redirect()->intended('/member/dashboard');
        }

        // Jika member baru: simpan data temporer Google ke Session -> Redirect ke /member/register/complete
        session([
            'google_user' => [
                'google_id'  => $googleUser->getId(),
                'name'       => $googleUser->getName() ?? $googleUser->getNickname() ?? '',
                'email'      => $googleUser->getEmail(),
                'photo_path' => $googleUser->getAvatar(),
            ],
        ]);

        return redirect()->route('member.register.complete');
    }
}
