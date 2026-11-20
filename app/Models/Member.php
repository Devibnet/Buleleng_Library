<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    /** Cara identitas diverifikasi (kolom identity_verified_via). */
    public const VERIFIED_ONLINE = 'mesin_online';
    public const VERIFIED_COUNTER = 'tatap_muka';

    /** Status akun yang menunggu pemeriksaan KTP asli oleh petugas. */
    public const STATUS_PENDING = 'Menunggu Verifikasi';

    /** Batas waktu (hari) pendaftar datang ke DAPD sebelum data pendaftaran dihapus otomatis. */
    public const COUNTER_VERIFICATION_DAYS = 14;

    /**
     * Kartu yang dapat dipakai mendaftar dan jenis nomor yang tercetak padanya.
     * Nomornya disimpan di kolom `nik` apa pun jenisnya; jenis kartu ada di `identity_document_type`.
     * (Harus sama dengan DOCUMENT_TYPES di atlas-frontend/src/utils/identityOcr.js.)
     */
    public const IDENTITY_DOCUMENTS = [
        'ktp'           => 'nik',
        'kia'           => 'nik',
        'ktm'           => 'nim',
        'kartu_pelajar' => 'nisn',
    ];

    /** Aturan tiap jenis nomor identitas (sama dengan NUMBER_KINDS di frontend). */
    public const IDENTITY_NUMBER_RULES = [
        'nik'  => ['label' => 'NIK', 'pattern' => '/^\d{16}$/', 'hint' => 'NIK harus berjumlah tepat 16 digit angka.'],
        'nim'  => ['label' => 'NIM', 'pattern' => '/^[A-Z0-9]{5,20}$/', 'hint' => 'NIM harus 5 sampai 20 karakter (huruf dan angka, tanpa spasi atau tanda baca).'],
        'nisn' => ['label' => 'NISN', 'pattern' => '/^\d{10}$/', 'hint' => 'NISN harus berjumlah tepat 10 digit angka.'],
    ];

    /** Jenis nomor ('nik' | 'nim' | 'nisn') untuk suatu kartu. Data lama tanpa jenis kartu (atau KK) memakai NIK. */
    public static function identityNumberKind(?string $documentType): string
    {
        return self::IDENTITY_DOCUMENTS[$documentType ?? ''] ?? 'nik';
    }

    public static function identityNumberLabel(?string $documentType): string
    {
        return self::IDENTITY_NUMBER_RULES[self::identityNumberKind($documentType)]['label'];
    }

    /** Bersihkan nomor identitas: NIK/NISN hanya angka, NIM huruf besar + angka. */
    public static function normalizeIdentityNumber(string $kind, mixed $value): string
    {
        $value = (string) $value;

        return $kind === 'nim'
            ? preg_replace('/[^A-Z0-9]/', '', strtoupper($value))
            : preg_replace('/\D/', '', $value);
    }

    /** Pesan galat bila nomor tidak sesuai aturan jenisnya, atau null bila sah. */
    public static function identityNumberError(string $kind, string $number): ?string
    {
        $rule = self::IDENTITY_NUMBER_RULES[$kind] ?? self::IDENTITY_NUMBER_RULES['nik'];

        return preg_match($rule['pattern'], $number) ? null : $rule['hint'];
    }

    /**
     * Anggota dengan nomor identitas tertentu pada jenis nomor yang sama. NIM dan NISN berasal dari
     * penomoran berbeda, jadi NISN seseorang tidak dianggap sama dengan NIM orang lain walau angkanya sama.
     */
    public function scopeWithIdentityNumber(Builder $query, string $kind, string $number): Builder
    {
        $types = array_keys(self::IDENTITY_DOCUMENTS, $kind, true);

        return $query->where('nik_hash', self::identityHash($number))->where(function (Builder $q) use ($kind, $types) {
            $q->whereIn('identity_document_type', $types);
            if ($kind === 'nik') {
                // Data lama: tanpa jenis kartu, atau jenis yang sudah tidak dipakai (Kartu Keluarga)
                $q->orWhereNull('identity_document_type')
                    ->orWhereNotIn('identity_document_type', array_keys(self::IDENTITY_DOCUMENTS));
            }
        });
    }

    // -------------------------------------------------------------------------
    // Kerahasiaan nomor identitas
    //
    // Kolom `nik` (NIK / NIM / NISN) disimpan TERENKRIPSI dan hanya dibuka untuk ditampilkan kepada
    // anggota itu sendiri. Petugas tidak pernah melihat nomor lengkapnya: mereka mengetik nomor dari kartu
    // asli, lalu sistem mencocokkannya lewat sidik `nik_hash`.
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::saving(function (Member $member) {
            if ($member->isDirty('nik') || ($member->nik !== null && $member->nik_hash === null)) {
                $member->nik_hash = self::identityHash($member->nik);
            }
        });
    }

    /**
     * Sidik nomor identitas (HMAC-SHA256 dengan kunci aplikasi) untuk cek nomor ganda dan pencarian nomor
     * lengkap. Huruf besar dan angka saja, sehingga "5108 0612…" dan "h1a-019" disidik sama dengan
     * bentuk tersimpannya.
     */
    public static function identityHash(?string $number): ?string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $number));

        return $normalized === '' ? null : hash_hmac('sha256', $normalized, 'atlas-identity-number|' . config('app.key'));
    }

    /** Anggota yang nomor identitasnya persis `$code` (dipakai di loket: pindai / ketik nomor lengkap). */
    public function scopeWhereIdentityNumber(Builder $query, ?string $code): Builder
    {
        $hash = self::identityHash($code);

        return $hash ? $query->where('nik_hash', $hash) : $query->whereRaw('1 = 0');
    }

    /** Nomor identitas tersamar untuk petugas: hanya 4 karakter terakhir, mis. "••••••••••••0002". */
    public static function maskIdentityNumber(?string $number): ?string
    {
        $number = (string) $number;
        $len = strlen($number);
        if ($len === 0) {
            return null;
        }

        $visible = $len >= 8 ? 4 : ($len >= 6 ? 2 : 1);

        return str_repeat('•', $len - $visible) . substr($number, -$visible);
    }

    public function getMaskedIdentityNumberAttribute(): ?string
    {
        return self::maskIdentityNumber($this->nik);
    }

    /** Apakah `$number` sama dengan nomor identitas tersimpan anggota ini (tanpa menampilkan nomornya). */
    public function identityNumberMatches(?string $number): bool
    {
        $hash = self::identityHash($number);

        return $hash !== null && $this->nik_hash !== null && hash_equals($this->nik_hash, $hash);
    }

    /**
     * Hapus akun pendaftar beserta berkas dan token login-nya (pendaftaran ditolak petugas, atau
     * tidak diverifikasi sampai batas waktunya).
     */
    public function deleteRegistration(): void
    {
        // Pas foto yang diunggah pendaftar (hanya berkas di storage kita, bukan URL avatar Google)
        if (is_string($this->photo_path) && str_starts_with($this->photo_path, '/storage/photos/members/')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete(substr($this->photo_path, strlen('/storage/')));
        }
        if (! empty($this->ktp_photo_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete(preg_replace('#^/?storage/#', '', $this->ktp_photo_path));
        }

        $this->tokens()->delete();
        $this->delete();
    }

    /**
     * Pendaftar yang memilih verifikasi tatap muka dan belum diperiksa petugas.
     * NIK pada akun seperti ini BELUM terbukti milik pendaftar, sehingga tidak memblokir pendaftar lain.
     */
    public function scopePendingCounter(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)->whereNotNull('verification_expires_at');
    }

    /** Semua akun kecuali yang masih menunggu verifikasi tatap muka. */
    public function scopeNotPendingCounter(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('verification_expires_at')
                ->orWhereRaw("COALESCE(status, '') <> ?", [self::STATUS_PENDING]);
        });
    }

    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'members';

    protected $fillable = [
        'member_no',
        'nik',
        'name',
        'gender',
        'residential_address',
        'ktp_address',
        'place_of_birth',
        'date_of_birth',
        'job_category',
        'institution_name',
        'max_loans',
        'phone_number',
        'email',
        'password',
        'photo_path',
        'ktp_photo_path',
        'status',
        'valid_until',
        'ktp_verified_at',
        'ktp_verified_by',
        'verification_rejection_reason',
        'google_id',
        'avatar',
        'data_consent_at',
        'data_consent_version',
        'identity_document_type',
        'identity_ocr_status',
        'identity_ocr_result',
        'identity_verified_via',
        'verification_expires_at',
        'must_change_password',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // Nomor identitas tidak pernah ikut terkirim saat model diubah ke JSON; lihat MemberResource
        'nik',
        'nik_hash',
    ];

    protected $appends = [
        'photo_url',
        'ktp_photo_url',
    ];

    /**
     * Get the resolved, absolute public URL for the member's photo or avatar.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $photo = $this->photo_path ?: $this->avatar;
        if (! $photo) {
            return null;
        }

        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return preg_replace('#/storage/+storage/#', '/storage/', $photo);
        }

        $cleaned = ltrim($photo, '/');
        $cleaned = preg_replace('#^storage/+storage/#', 'storage/', $cleaned);
        if (str_starts_with($cleaned, 'storage/')) {
            return asset($cleaned);
        }

        return asset('storage/' . $cleaned);
    }

    /**
     * Get the resolved, absolute public URL for the member's KTP photo during verification.
     */
    public function getKtpPhotoUrlAttribute(): ?string
    {
        if (! $this->ktp_photo_path) {
            return null;
        }

        if (str_starts_with($this->ktp_photo_path, 'http://') || str_starts_with($this->ktp_photo_path, 'https://')) {
            return $this->ktp_photo_path;
        }

        $cleaned = ltrim($this->ktp_photo_path, '/');
        if (str_starts_with($cleaned, 'storage/')) {
            return asset($cleaned);
        }

        return asset('storage/' . $cleaned);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'valid_until' => 'date',
            'ktp_verified_at' => 'datetime',
            'data_consent_at' => 'datetime',
            'identity_ocr_result' => 'array',
            'verification_expires_at' => 'datetime',
            'nik' => 'encrypted',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * Katalog buku yang ditandai favorit (bintang) oleh anggota.
     */
    public function favoriteCatalogs(): BelongsToMany
    {
        return $this->belongsToMany(Catalog::class, 'member_favorites')->withTimestamps();
    }

    /**
     * Alasan hak pinjam / reservasi anggota diblokir, atau null jika anggota boleh bertransaksi.
     * Dipakai bersama oleh loket Peminjaman (admin) dan Reservasi Mandiri (anggota).
     */
    public function circulationBlockReason(): ?string
    {
        $status = $this->status ?: 'Aktif Bebas Pustaka';

        if (! str_starts_with(strtolower($status), 'aktif')) {
            return "Akses pinjam anggota sedang tidak aktif (Status: {$status}).";
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return 'Masa berlaku kartu anggota telah habis pada ' . $this->valid_until->format('d/m/Y') . '.';
        }

        if (MemberCart::where('member_id', $this->id)->where('is_resolved', false)->exists()) {
            return 'Anggota masih memiliki kasus di Keranjang Bermasalah yang belum diselesaikan.';
        }

        return null;
    }

    /**
     * Generate unique member number with format MEM-YYYYMM-XXXX
     * Example: MEM-202609-0001
     */
    public static function generateMemberNo(): string
    {
        $prefix = 'MEM-' . now()->format('Ym') . '-';

        $lastMember = static::where('member_no', 'like', $prefix . '%')
            ->orderBy('member_no', 'desc')
            ->first();

        $sequence = 1;
        if ($lastMember) {
            $lastSequence = (int) substr($lastMember->member_no, strlen($prefix));
            $sequence = $lastSequence + 1;
        }

        do {
            $candidateNo = $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $exists = static::where('member_no', $candidateNo)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $candidateNo;
    }

    /**
     * Generate member number matching ATLAS UI: ATL-YYYY-XXXXX
     * Example: ATL-2026-00194
     */
    public static function generateAtlasMemberNo(string $prefixTag = 'ATL'): string
    {
        $year = now()->format('Y');
        $prefix = "{$prefixTag}-{$year}-";

        $lastMember = static::where('member_no', 'like', $prefix . '%')
            ->orWhere('member_no', 'like', "AGT-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 194; // Realistic seed base or sequential
        if ($lastMember && preg_match('/(\d+)$/', $lastMember->member_no, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $candidateNo = $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
            $exists = static::where('member_no', $candidateNo)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $candidateNo;
    }

    public function memberCarts(): HasMany
    {
        return $this->hasMany(MemberCart::class, 'member_id');
    }

    public function guestbooks(): HasMany
    {
        return $this->hasMany(Guestbook::class, 'member_id');
    }

    public function lockerLoans(): HasMany
    {
        return $this->hasMany(LockerLoan::class, 'member_id');
    }

    public function onsiteReadings(): HasMany
    {
        return $this->hasMany(OnsiteReading::class, 'member_id');
    }

    public function reservedCollections(): HasMany
    {
        return $this->hasMany(ReservedCollection::class, 'member_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'member_id');
    }
}
