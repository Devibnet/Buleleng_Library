<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberRegisterController extends Controller
{
    /**
     * Tampilkan formulir registrasi lanjutan bagi calon anggota dari Google OAuth.
     */
    public function showRegistrationForm(Request $request): View|RedirectResponse
    {
        // Jika sudah login sebagai member, langsung arahkan ke dashboard
        if (Auth::guard('member')->check()) {
            return redirect('/member/dashboard');
        }

        $googleUser = session('google_user');

        // Pastikan ada data temporer Google di Session
        if (!$googleUser || empty($googleUser['email'])) {
            return redirect('/')
                ->with('error', 'Sesi pendaftaran Google tidak ditemukan atau telah kedaluwarsa. Silakan masuk kembali dengan Google.');
        }

        return view('member.register-complete', compact('googleUser'));
    }

    /**
     * Proses penyimpanan registrasi lanjutan ke tabel members.
     */
    public function completeRegistration(Request $request): RedirectResponse
    {
        $googleUser = session('google_user');

        if (!$googleUser || empty($googleUser['email'])) {
            return redirect('/')
                ->with('error', 'Sesi pendaftaran Google telah kedaluwarsa. Silakan mulai kembali dari awal.');
        }

        // Validasi seluruh inputan form wajib
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'place_of_birth'      => ['required', 'string', 'max:255'],
            'date_of_birth'       => ['required', 'date', 'before:today'],
            'ktp_address'         => ['required', 'string'],
            'residential_address' => ['required', 'string'],
            'job_category'        => ['required', 'in:UMUM,PELAJAR,MAHASISWA'],
            'institution_name'    => ['required', 'string', 'max:255'],
            'phone_number'        => ['required', 'string', 'max:25'],
            'photo'               => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ], [
            'name.required'                => 'Nama lengkap wajib diisi.',
            'place_of_birth.required'      => 'Tempat lahir wajib diisi.',
            'date_of_birth.required'       => 'Tanggal lahir wajib diisi.',
            'date_of_birth.before'         => 'Tanggal lahir harus sebelum hari ini.',
            'ktp_address.required'         => 'Alamat KTP wajib diisi.',
            'residential_address.required' => 'Alamat tinggal saat ini wajib diisi.',
            'job_category.required'        => 'Kategori pekerjaan wajib dipilih.',
            'job_category.in'              => 'Kategori pekerjaan harus salah satu dari: UMUM, PELAJAR, atau MAHASISWA.',
            'institution_name.required'    => 'Nama instansi/sekolah/kampus/tempat kerja wajib diisi.',
            'phone_number.required'        => 'Nomor telepon/WhatsApp wajib diisi.',
            'photo.required'               => 'Foto wajib diunggah untuk kartu anggota.',
            'photo.image'                  => 'File yang diunggah harus berupa gambar.',
            'photo.mimes'                  => 'Format gambar yang diperbolehkan: jpeg, png, jpg.',
            'photo.max'                    => 'Ukuran maksimal file gambar adalah 2MB.',
        ]);

        // Cek apakah email sudah terdaftar sebelumnya (antisipasi double submit)
        $existingMember = Member::where('email', $googleUser['email'])->first();
        if ($existingMember) {
            session()->forget('google_user');
            Auth::guard('member')->login($existingMember);
            return redirect('/member/dashboard')->with('info', 'Akun Anda sudah terdaftar sebelumnya.');
        }

        // Handle File Upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $file = $request->request->all() ? $request->file('photo') : null; // sanity check
            if ($file && !$file->isValid()) {
                return back()
                    ->withErrors(['photo' => 'File gagal diunggah. Pastikan ukuran gambar tidak melebihi batas (Maks. 2MB).'])
                    ->withInput();
            }
            
            if ($file) {
                // Gunakan Storage facade dan hashName, menghindari error getRealPath() di Windows
                $filename = $file->hashName();
                \Illuminate\Support\Facades\Storage::disk('public')->put(
                    'photos/members/' . $filename, 
                    file_get_contents($file->getPathname())
                );
                
                // Format path for storage: url
                $photoPath = '/storage/photos/members/' . $filename;
            }
        }

        // Auto-generate member_no unik (format: MEM-YYYYMM-XXXX)
        $memberNo = Member::generateMemberNo();

        // Simpan data ke tabel members
        $member = Member::create([
            'member_no'           => $memberNo,
            'name'                => $validated['name'],
            'residential_address' => $validated['residential_address'],
            'ktp_address'         => $validated['ktp_address'],
            'place_of_birth'      => $validated['place_of_birth'],
            'date_of_birth'       => $validated['date_of_birth'],
            'job_category'        => $validated['job_category'],
            'institution_name'    => $validated['institution_name'],
            'phone_number'        => $validated['phone_number'],
            'email'               => $googleUser['email'],
            'password'            => Hash::make(Str::random(16)),
            'photo_path'          => $photoPath, // Use uploaded photo
            'google_id'           => $googleUser['google_id'] ?? null,
            'avatar'              => $googleUser['photo_path'] ?? null, // Keep original google avatar in avatar col if it exists
        ]);

        // Hapus session temporer Google setelah berhasil
        session()->forget('google_user');

        // Login-kan member menggunakan guard member
        Auth::guard('member')->login($member);

        // Cek apakah ada intent reservasi tertunda pasca registrasi
        $intendedBookId = session('intended_book_id');
        if ($intendedBookId) {
            session()->forget('intended_book_id');
            return redirect("/books/{$intendedBookId}")
                ->with('success', 'Registrasi anggota berhasil! Silakan lanjutkan reservasi buku Anda.');
        }

        // Redirect ke /member/dashboard
        return redirect('/member/dashboard')->with('success', 'Selamat! Registrasi anggota berhasil. Nomor anggota Anda: ' . $member->member_no);
    }
}
