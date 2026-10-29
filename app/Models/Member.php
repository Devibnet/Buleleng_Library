<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
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
        'status',
        'valid_until',
        'google_id',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'photo_url',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'valid_until' => 'date',
            'password' => 'hashed',
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
