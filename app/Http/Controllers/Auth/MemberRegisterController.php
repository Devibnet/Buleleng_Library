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
    /** Versi teks Pernyataan Persetujuan Pemrosesan Data Pribadi (harus sama dengan frontend). */
    public const DATA_CONSENT_VERSION = '2026-11-12-1';

    /** Kartu yang dapat dipakai mendaftar: KTP dan KIA (NIK), KTM (NIM), Kartu Pelajar (NISN). */
    public const IDENTITY_DOCUMENT_TYPES = ['ktp', 'kia', 'ktm', 'kartu_pelajar'];

    public const IDENTITY_OCR_STATUSES = ['matched', 'mismatch', 'unreadable', 'not_applicable', 'unchecked'];

    private function numberTakenMessage(string $kind): string
    {
        $label = Member::IDENTITY_NUMBER_RULES[$kind]['label'];

        return "{$label} ini sudah terdaftar sebagai anggota perpustakaan. Jika itu milik Anda, silakan masuk menggunakan email yang terdaftar atau hubungi petugas.";
    }

    /**
     * Anggota lain yang sudah memakai nomor identitas ini (NIK / NIM / NISN). Akun yang masih menunggu
     * verifikasi tatap muka tidak dihitung, karena nomornya belum terbukti milik pendaftar (mencegah
     * "memesan" nomor orang lain).
     */
    private function identityNumberOwner(string $kind, string $number, ?int $ignoreId = null): ?Member
    {
        return Member::notPendingCounter()
            ->withIdentityNumber($kind, $number)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first(['id', 'email']);
    }

    /**
     * Cek nomor identitas ganda langsung dari formulir (POST /api/v1/auth/member/check-nik).
     * `document_type` menentukan jenis nomornya (default KTP → NIK).
     * Dibatasi throttle di route agar tidak dipakai menebak-nebak nomor orang lain.
     */
    public function checkNik(Request $request): \Illuminate\Http\JsonResponse
    {
        $kind = Member::identityNumberKind($request->input('document_type'));
        $number = Member::normalizeIdentityNumber($kind, $request->input('nik'));
        if ($error = Member::identityNumberError($kind, $number)) {
            return response()->json([
                'success' => false,
                'message' => $error,
            ], 422);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $owner = $this->identityNumberOwner($kind, $number);
        // Pendaftar Google yang melengkapi datanya sendiri tidak dianggap duplikat
        $available = ! $owner || ($email !== '' && strtolower((string) $owner->email) === $email);

        return response()->json([
            'success'   => true,
            'available' => $available,
            'message'   => $available
                ? Member::IDENTITY_NUMBER_RULES[$kind]['label'] . ' dapat digunakan.'
                : $this->numberTakenMessage($kind),
        ]);
    }

    /**
     * Catatan hasil pemeriksaan foto di browser pendaftar. Hanya status dan jumlah percobaan yang
     * disimpan; nomor hasil baca mesin TIDAK disimpan (bisa saja milik orang lain pada foto yang salah).
     */
    private function sanitizeIdentityOcr(array $validated): array
    {
        $type = $validated['identity_document_type'] ?? null;
        $status = $validated['identity_ocr_status'] ?? 'unchecked';
        $raw = json_decode((string) ($validated['identity_ocr_result'] ?? ''), true);
        $raw = is_array($raw) ? $raw : [];

        return [
            'identity_document_type' => $type,
            'identity_ocr_status'    => $status,
            'identity_ocr_result'    => [
                'attempts'   => isset($raw['attempts']) ? max(0, min(50, (int) $raw['attempts'])) : null,
                'fuzzy'      => (bool) ($raw['fuzzy'] ?? false),
                'checked_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Tentukan status akun dari cara verifikasi yang dipilih pendaftar.
     *
     *  - online  : NIK/NIM/NISN cocok dengan foto kartu (diperiksa di browser; foto tidak dikirim) → akun langsung aktif.
     *  - counter : pendaftar memilih verifikasi di DAPD → menunggu; dihapus bila ditolak petugas atau tidak datang.
     *
     * Mengembalikan [atribut akun, pesan galat|null].
     */
    private function verificationAttributes(string $mode, array $identity, bool $onlineAllowed): array
    {
        if ($mode === 'online') {
            if (! $onlineAllowed) {
                return [[], 'Akun ini harus diverifikasi langsung oleh petugas di DAPD Buleleng. Pilih "Tetap daftar" untuk verifikasi tatap muka.'];
            }
            if (! in_array($identity['identity_document_type'], self::IDENTITY_DOCUMENT_TYPES, true)
                || $identity['identity_ocr_status'] !== 'matched') {
                return [[], Member::identityNumberLabel($identity['identity_document_type'])
                    . ' belum terverifikasi secara online. Pilih "Tetap daftar" untuk verifikasi langsung di DAPD Buleleng.'];
            }

            return [[
                'status'                   => 'Aktif Bebas Pustaka',
                'valid_until'              => now()->addYears(5),
                'identity_verified_via'    => Member::VERIFIED_ONLINE,
                'verification_expires_at'  => null,
                'verification_rejection_reason' => null,
            ], null];
        }

        return [[
            'status'                   => Member::STATUS_PENDING,
            'identity_verified_via'    => null,
            'verification_expires_at'  => now()->addDays(Member::COUNTER_VERIFICATION_DAYS),
            'verification_rejection_reason' => null,
        ], null];
    }

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
            'nik'                 => ['required', 'digits:16', function ($attribute, $value, $fail) {
                if ($this->identityNumberOwner('nik', (string) $value)) {
                    $fail('NIK ini sudah terdaftar sebagai anggota perpustakaan.');
                }
            }],
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

        // Foto dokumen identitas TIDAK diunggah maupun disimpan. Formulir ini tidak punya pemeriksaan
        // NIK online, jadi akun menunggu verifikasi tatap muka di DAPD Buleleng.
        [$verification] = $this->verificationAttributes('counter', [], false);

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
            'max_loans'           => 3,
            'google_id'           => $googleUser['google_id'] ?? null,
            'avatar'              => $googleUser['photo_path'] ?? null,
            ...$verification,
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
        return redirect('/member/dashboard')->with('success', 'Pendaftaran diterima. Nomor anggota Anda: ' . $member->member_no
            . '. Datang ke DAPD Buleleng membawa KTP asli sebelum ' . $member->verification_expires_at->locale('id')->translatedFormat('d F Y') . ' untuk verifikasi keanggotaan.');
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

        // Foto dokumen identitas (ktp_photo dan alias-nya) sengaja DIABAIKAN: tidak divalidasi, tidak
        // disimpan. Pemeriksaan NIK terhadap foto dilakukan di browser pendaftar, dan foto tidak dikirim.

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

        // Jenis nomor mengikuti kartu yang dipilih: KTP/KIA → NIK, KTM → NIM, Kartu Pelajar → NISN.
        // Nomornya tetap dikirim dan disimpan pada field `nik`.
        $documentType = $input['identity_document_type'] ?? $existingMember?->identity_document_type;
        $numberKind = Member::identityNumberKind(is_string($documentType) ? $documentType : null);
        $numberLabel = Member::IDENTITY_NUMBER_RULES[$numberKind]['label'];
        if (! isset($input['nik']) && isset($input['identity_number'])) {
            $input['nik'] = $input['identity_number'];
        }
        if (isset($input['nik']) && is_scalar($input['nik'])) {
            $input['nik'] = Member::normalizeIdentityNumber($numberKind, $input['nik']);
        }

        $nikRule = ['required', 'string', function ($attribute, $value, $fail) use ($numberKind, $existingMember) {
            if ($error = Member::identityNumberError($numberKind, (string) $value)) {
                $fail($error);
            } elseif ($this->identityNumberOwner($numberKind, (string) $value, $existingMember?->id)) {
                $fail($this->numberTakenMessage($numberKind));
            }
        }];

        // Akun yang sudah aktif (mis. anggota lama melengkapi data) tidak perlu verifikasi ulang.
        $existingIsActive = $existingMember && str_starts_with(strtolower((string) $existingMember->status), 'aktif');
        $mustVerify = ! $existingIsActive;
        // Verifikasi online hanya untuk pendaftar baru atau akun Google yang belum pernah melengkapi NIK.
        // Akun yang sudah punya NIK (mis. pernah ditolak petugas) harus diperiksa tatap muka lagi.
        $onlineAllowed = ! $existingMember || blank($existingMember->nik);

        $passwordRule = $isExistingGoogleOrUpdating
            ? ['nullable', 'string', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()]
            : ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()];

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
            'data_consent'        => ['accepted'],
            'verification_mode'   => [$mustVerify ? 'required' : 'nullable', 'in:online,counter'],
            'counter_agreement'   => ['required_if:verification_mode,counter', 'accepted_if:verification_mode,counter'],
            'identity_document_type' => [$mustVerify ? 'required' : 'nullable', 'in:' . implode(',', self::IDENTITY_DOCUMENT_TYPES)],
            'identity_ocr_status'    => ['nullable', 'in:' . implode(',', self::IDENTITY_OCR_STATUSES)],
            'identity_ocr_result'    => ['nullable', 'string', 'max:1000'],
        ], [
            'identity_document_type.required' => 'Pilih kartu yang digunakan: KTP, KIA, KTM, atau Kartu Pelajar.',
            'identity_document_type.in' => 'Kartu yang dapat digunakan hanya KTP, KIA, KTM, atau Kartu Pelajar.',
            'verification_mode.required' => "Pilih cara verifikasi {$numberLabel}: verifikasi online, atau datang ke DAPD Buleleng.",
            'verification_mode.in'       => 'Cara verifikasi tidak dikenali.',
            'counter_agreement.required_if' => 'Centang persetujuan untuk datang ke DAPD Buleleng agar pendaftaran dapat diproses.',
            'counter_agreement.accepted_if' => 'Centang persetujuan untuk datang ke DAPD Buleleng agar pendaftaran dapat diproses.',
            'data_consent.accepted' => 'Anda harus menyetujui Persetujuan Pemrosesan Data Pribadi untuk mendaftar.',
            'nik.required'          => "{$numberLabel} wajib diisi.",
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $identityOcr = $this->sanitizeIdentityOcr($validated);
        $mode = $validated['verification_mode'] ?? null;

        $verification = [];
        if ($mustVerify) {
            [$verification, $verificationError] = $this->verificationAttributes($mode, $identityOcr, $onlineAllowed);
            if ($verificationError) {
                return response()->json([
                    'success' => false,
                    'message' => $verificationError,
                    'errors'  => ['verification_mode' => [$verificationError]],
                ], 422);
            }
            $verification = array_merge($verification, $identityOcr);
        }

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
                'data_consent_at'      => now(),
                'data_consent_version' => self::DATA_CONSENT_VERSION,
            ];
            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }
            if ($photoPath) {
                $updateData['photo_path'] = $photoPath;
            }
            // Jenis kartu harus selalu sejalan dengan jenis nomor yang tersimpan di kolom `nik`
            if (! empty($validated['identity_document_type'])) {
                $updateData['identity_document_type'] = $validated['identity_document_type'];
            }
            // Status akun hanya berubah bila akun belum aktif (lihat $mustVerify)
            $updateData = array_merge($updateData, $verification);
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
                'max_loans'           => 3,
                'data_consent_at'      => now(),
                'data_consent_version' => self::DATA_CONSENT_VERSION,
                ...$verification,
            ]);
        }

        // Buat token autentikasi Sanctum
        $token = $member->createToken('member-auth-token')->plainTextToken;

        if (! $mustVerify) {
            $message = 'Data anggota berhasil diperbarui.';
        } elseif ($mode === 'online') {
            $message = "Registrasi berhasil! {$numberLabel} Anda terverifikasi dan akun anggota sudah aktif.";
        } else {
            $message = 'Pendaftaran diterima, tetapi akun belum aktif. Datang ke DAPD Buleleng dan tunjukkan kartu identitas asli Anda (KTP / KIA / KTM / Kartu Pelajar) sebelum '
                . $member->verification_expires_at->locale('id')->translatedFormat('d F Y')
                . ' untuk verifikasi keanggotaan. Akun dihapus bila verifikasi ditolak atau tidak dilakukan sampai batas waktu tersebut.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'token'   => $token,
            'data'    => [
                'token'  => $token,
                'member' => new \App\Http\Resources\MemberResource($member),
                'verification' => [
                    'mode'       => $mustVerify ? $mode : null,
                    'status'     => $member->status,
                    'expires_at' => $member->verification_expires_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }
}
