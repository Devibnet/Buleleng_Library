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
            'nik'                 => ['required', 'digits:16', 'unique:members,nik'],
            'name'                => ['required', 'string', 'max:255'],
            'place_of_birth'      => ['required', 'string', 'max:255'],
            'date_of_birth'       => ['required', 'date', 'before:today'],
            'ktp_address'         => ['required', 'string'],
            'residential_address' => ['required', 'string'],
            'job_category'        => ['required', 'in:UMUM,PELAJAR,MAHASISWA'],
            'institution_name'    => ['required', 'string', 'max:255'],
            'phone_number'        => ['required', 'string', 'max:25'],
            'photo'               => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'ktp_photo'           => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ], [
            'nik.required'                 => 'Nomor Induk Kependudukan (NIK) wajib diisi.',
            'nik.digits'                   => 'NIK harus berjumlah tepat 16 digit angka.',
            'nik.unique'                   => 'NIK ini sudah terdaftar sebagai anggota perpustakaan.',
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
            'ktp_photo.required'           => 'Foto dokumen identitas resmi (e-KTP / KIA / Kartu Pelajar / Kartu Keluarga) wajib diunggah untuk verifikasi keanggotaan online.',
            'ktp_photo.image'              => 'Berkas foto dokumen identitas harus berupa gambar.',
            'ktp_photo.mimes'              => 'Format gambar identitas yang diperbolehkan: jpeg, png, jpg.',
            'ktp_photo.max'                => 'Ukuran foto dokumen identitas maksimal 2MB.',
        ]);

        // Cek apakah email sudah terdaftar sebelumnya (antisipasi double submit)
        $existingMember = Member::where('email', $googleUser['email'])->first();
        if ($existingMember) {
            session()->forget('google_user');
            Auth::guard('member')->login($existingMember);
            return redirect('/member/dashboard')->with('info', 'Akun Anda sudah terdaftar sebelumnya.');
        }

        // Handle File Upload Pas Foto
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            if ($file && $file->isValid()) {
                $filename = $file->hashName();
                \Illuminate\Support\Facades\Storage::disk('public')->put(
                    'photos/members/' . $filename, 
                    file_get_contents($file->getPathname())
                );
                $photoPath = '/storage/photos/members/' . $filename;
            }
        }

        // Handle File Upload KTP Bali
        $ktpPhotoPath = null;
        if ($request->hasFile('ktp_photo')) {
            $ktpFile = $request->file('ktp_photo');
            if ($ktpFile && $ktpFile->isValid()) {
                $ktpFilename = 'ktp_' . Str::random(24) . '.' . $ktpFile->getClientOriginalExtension();
                \Illuminate\Support\Facades\Storage::disk('public')->put(
                    'ktp-verifications/' . $ktpFilename,
                    file_get_contents($ktpFile->getPathname())
                );
                $ktpPhotoPath = '/storage/ktp-verifications/' . $ktpFilename;
            }
        }

        // Auto-generate member_no unik (format: MEM-YYYYMM-XXXX)
        $memberNo = Member::generateMemberNo();

        // Simpan data ke tabel members
        $member = Member::create([
            'member_no'           => $memberNo,
            'nik'                 => $validated['nik'],
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
            'photo_path'          => $photoPath,
            'ktp_photo_path'      => $ktpPhotoPath,
            'status'              => 'Menunggu Verifikasi',
            'max_loans'           => 3,
            'google_id'           => $googleUser['google_id'] ?? null,
            'avatar'              => $googleUser['photo_path'] ?? null,
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

    /**
     * API Register Member Endpoint (POST /api/auth/member/register)
     */
    public function registerApi(Request $request): \Illuminate\Http\JsonResponse
    {
        // Normalisasi input jika ada alias nama field dari frontend
        $input = $request->all();

        if (!isset($input['name']) && isset($input['namaLengkap'])) {
            $input['name'] = $input['namaLengkap'];
        }
        if (!isset($input['nik']) && isset($input['noKtp'])) {
            $input['nik'] = $input['noKtp'];
        }
        if (!isset($input['residential_address']) && isset($input['alamatTinggal'])) {
            $input['residential_address'] = $input['alamatTinggal'];
        }
        if (!isset($input['ktp_address']) && isset($input['alamatKtp'])) {
            $input['ktp_address'] = $input['alamatKtp'];
        }
        if (!isset($input['institution_name']) && isset($input['instansi'])) {
            $input['institution_name'] = $input['instansi'];
        }
        if (!isset($input['phone_number']) && isset($input['noHandphone'])) {
            $input['phone_number'] = $input['noHandphone'];
        }
        if (!isset($input['place_of_birth']) && isset($input['tempatLahir'])) {
            $input['place_of_birth'] = $input['tempatLahir'];
        }
        if (!isset($input['date_of_birth']) && isset($input['tanggalLahir'])) {
            $input['date_of_birth'] = $input['tanggalLahir'];
        }
        if (isset($input['pekerjaan']) && !isset($input['job_category'])) {
            $input['job_category'] = strtoupper($input['pekerjaan']);
        } elseif (isset($input['job_category'])) {
            $input['job_category'] = strtoupper($input['job_category']);
        }

        // Tangkap file upload ke input array agar lolos validasi Validator::make
        $photoFile = $request->file('photo') ?? $request->file('photo_formal') ?? $request->file('foto') ?? $request->file('fotoFormal');
        if ($photoFile) {
            $input['photo'] = $photoFile;
        }

        $ktpFile = $request->file('ktp_photo') ?? $request->file('ktpPhoto') ?? $request->file('foto_ktp') ?? $request->file('fotoKtp');
        if ($ktpFile) {
            $input['ktp_photo'] = $ktpFile;
        }

        // Cek jika tempatTanggalLahir dikirim dalam satu string (misal: "Singaraja, 2000-05-15")
        if ((empty($input['place_of_birth']) || empty($input['date_of_birth'])) && !empty($input['tempatTanggalLahir'])) {
            $ttlParts = explode(',', $input['tempatTanggalLahir'], 2);
            if (empty($input['place_of_birth'])) {
                $input['place_of_birth'] = trim($ttlParts[0]);
            }
            if (empty($input['date_of_birth']) && isset($ttlParts[1])) {
                $rawDate = trim($ttlParts[1]);
                try {
                    $input['date_of_birth'] = \Carbon\Carbon::parse($rawDate)->format('Y-m-d');
                } catch (\Exception $e) {
                    $input['date_of_birth'] = $rawDate;
                }
            }
        }

        $existingMember = !empty($input['email']) ? Member::where('email', $input['email'])->first() : null;
        $isExistingGoogleOrUpdating = $existingMember !== null;

        $emailRule = ['required', 'string', 'email', 'max:255', 'regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]{2,}(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/'];
        if ($isExistingGoogleOrUpdating) {
            $emailRule[] = \Illuminate\Validation\Rule::unique('members', 'email')->ignore($existingMember->id);
        } else {
            $emailRule[] = 'unique:members,email';
        }

        $nikRule = ['required', 'string', 'digits:16'];
        if ($isExistingGoogleOrUpdating) {
            $nikRule[] = \Illuminate\Validation\Rule::unique('members', 'nik')->ignore($existingMember->id);
        } else {
            $nikRule[] = 'unique:members,nik';
        }

        $passwordRule = $isExistingGoogleOrUpdating
            ? ['nullable', 'string', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()]
            : ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()];

        $ktpPhotoRule = ($isExistingGoogleOrUpdating && $existingMember->status === 'Aktif Bebas Pustaka')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048']
            : ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'];

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'nik'                 => $nikRule,
            'name'                => ['required', 'string', 'max:255'],
            'email'               => $emailRule,
            'password'            => $passwordRule,
            'place_of_birth'      => ['nullable', 'string', 'max:255'],
            'date_of_birth'       => ['nullable', 'date', 'before:today'],
            'ktp_address'         => ['nullable', 'string'],
            'residential_address' => ['nullable', 'string'],
            'job_category'        => ['required', 'in:UMUM,PELAJAR,MAHASISWA'],
            'institution_name'    => ['nullable', 'string', 'max:255'],
            'phone_number'        => ['nullable', 'string', 'max:25'],
            'photo'               => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'ktp_photo'           => $ktpPhotoRule,
        ], [
            'nik.required'          => 'Nomor Induk Kependudukan (NIK) wajib diisi.',
            'nik.digits'            => 'NIK harus berjumlah tepat 16 digit angka.',
            'nik.unique'            => 'NIK ini sudah terdaftar sebagai anggota perpustakaan.',
            'name.required'         => 'Nama lengkap wajib diisi.',
            'email.required'        => 'Email wajib diisi.',
            'email.email'           => 'Format email tidak valid.',
            'email.regex'           => 'Format email tidak valid. Contoh: nama@domain.com',
            'email.unique'          => 'Email ini sudah terdaftar sebagai anggota perpustakaan.',
            'password.required'     => 'Password wajib diisi.',
            'password.min'          => 'Password minimal harus 8 karakter.',
            'password'              => 'Password minimal harus 8 karakter, mengandung setidaknya 1 huruf besar, 1 huruf kecil, 1 angka, dan 1 simbol.',
            'date_of_birth.date'    => 'Format tanggal lahir tidak valid.',
            'date_of_birth.before'  => 'Tanggal lahir harus sebelum hari ini.',
            'job_category.required' => 'Kategori pekerjaan wajib dipilih.',
            'job_category.in'       => 'Kategori pekerjaan harus salah satu dari: UMUM, PELAJAR, atau MAHASISWA.',
            'photo.image'           => 'File pas foto harus berupa gambar.',
            'photo.mimes'           => 'Format gambar pas foto yang diperbolehkan: jpeg, png, jpg.',
            'photo.max'             => 'Ukuran maksimal file pas foto adalah 2MB.',
            'ktp_photo.required'    => 'Foto dokumen identitas resmi (e-KTP / KIA / Kartu Pelajar / Kartu Keluarga) wajib diunggah untuk verifikasi keanggotaan online.',
            'ktp_photo.image'       => 'Berkas foto dokumen identitas harus berupa gambar.',
            'ktp_photo.mimes'       => 'Format gambar identitas yang diperbolehkan: jpeg, png, jpg.',
            'ktp_photo.max'         => 'Ukuran foto dokumen identitas maksimal 2MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Handle File Upload Pas Foto
        $photoPath = null;
        if ($photoFile && $photoFile->isValid()) {
            $filename = $photoFile->hashName();
            \Illuminate\Support\Facades\Storage::disk('public')->put(
                'photos/members/' . $filename,
                file_get_contents($photoFile->getPathname())
            );
            $photoPath = '/storage/photos/members/' . $filename;
        }

        // Handle File Upload KTP Bali
        $ktpPhotoPath = null;
        if ($ktpFile && $ktpFile->isValid()) {
            $ktpFilename = 'ktp_' . Str::random(24) . '.' . $ktpFile->getClientOriginalExtension();
            \Illuminate\Support\Facades\Storage::disk('public')->put(
                'ktp-verifications/' . $ktpFilename,
                file_get_contents($ktpFile->getPathname())
            );
            $ktpPhotoPath = '/storage/ktp-verifications/' . $ktpFilename;
        }

        if ($existingMember) {
            $updateData = [
                'nik'                 => $validated['nik'],
                'name'                => $validated['name'],
                'place_of_birth'      => $validated['place_of_birth'] ?? $existingMember->place_of_birth,
                'date_of_birth'       => $validated['date_of_birth'] ?? $existingMember->date_of_birth,
                'ktp_address'         => $validated['ktp_address'] ?? $existingMember->ktp_address,
                'residential_address' => $validated['residential_address'] ?? $existingMember->residential_address,
                'job_category'        => $validated['job_category'],
                'institution_name'    => $validated['institution_name'] ?? $existingMember->institution_name,
                'phone_number'        => $validated['phone_number'] ?? $existingMember->phone_number,
                'max_loans'           => 3,
            ];
            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }
            if ($photoPath) {
                $updateData['photo_path'] = $photoPath;
            }
            if ($ktpPhotoPath) {
                $updateData['ktp_photo_path'] = $ktpPhotoPath;
                $updateData['status'] = 'Menunggu Verifikasi';
                $updateData['verification_rejection_reason'] = null;
            } elseif (empty($existingMember->status) || $existingMember->status === 'Ditolak') {
                $updateData['status'] = 'Menunggu Verifikasi';
                $updateData['verification_rejection_reason'] = null;
            }
            if (empty($existingMember->member_no)) {
                $updateData['member_no'] = Member::generateMemberNo();
            }

            $existingMember->update($updateData);
            $member = $existingMember->fresh();
        } else {
            // Generate member_no
            $memberNo = Member::generateMemberNo();

            // Create Member
            $member = Member::create([
                'member_no'           => $memberNo,
                'nik'                 => $validated['nik'],
                'name'                => $validated['name'],
                'email'               => $validated['email'],
                'password'            => Hash::make($validated['password']),
                'place_of_birth'      => $validated['place_of_birth'] ?? null,
                'date_of_birth'       => $validated['date_of_birth'] ?? null,
                'ktp_address'         => $validated['ktp_address'] ?? null,
                'residential_address' => $validated['residential_address'] ?? null,
                'job_category'        => $validated['job_category'],
                'institution_name'    => $validated['institution_name'] ?? null,
                'phone_number'        => $validated['phone_number'] ?? null,
                'photo_path'          => $photoPath,
                'ktp_photo_path'      => $ktpPhotoPath,
                'status'              => 'Menunggu Verifikasi',
                'max_loans'           => 3,
            ]);
        }

        // Buat token autentikasi Sanctum
        $token = $member->createToken('member-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi anggota berhasil! Akun Anda sedang dalam antrean verifikasi KTP Bali oleh petugas.',
            'token'   => $token,
            'data'    => [
                'token'  => $token,
                'member' => new \App\Http\Resources\MemberResource($member),
            ],
        ], 201);
    }
}
