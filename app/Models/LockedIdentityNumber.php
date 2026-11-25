<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Kunci nomor identitas milik akun yang dihapus pemiliknya saat masih punya kasus di Keranjang Bermasalah.
 * Nomor asli tidak disimpan: pencocokan memakai sidik `nik_hash` (lihat Member::identityHash).
 */
class LockedIdentityNumber extends Model
{
    protected $table = 'locked_identity_numbers';

    protected $fillable = [
        'nik_hash',
        'number_kind',
        'masked_number',
        'former_member_no',
        'cases',
        'locked_at',
        'unlocked_at',
        'unlocked_by',
        'unlocked_by_name',
        'unlock_note',
    ];

    protected $hidden = ['nik_hash'];

    protected function casts(): array
    {
        return [
            'cases'       => 'array',
            'locked_at'   => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unlocked_at');
    }

    /** Apakah nomor ini sedang dikunci untuk jenis nomornya ('nik' | 'nim' | 'nisn'). */
    public static function isLocked(string $kind, ?string $number): bool
    {
        $hash = Member::identityHash($number);

        return $hash !== null && self::active()->where('nik_hash', $hash)->where('number_kind', $kind)->exists();
    }

    /** Pesan untuk pendaftar yang nomornya terkunci. */
    public static function lockedMessage(string $kind): string
    {
        $label = Member::IDENTITY_NUMBER_RULES[$kind]['label'] ?? 'Nomor identitas';

        return "{$label} ini dikunci karena akun lamanya dihapus saat masih ada kasus yang belum selesai (misalnya buku belum dikembalikan atau denda). Selesaikan dulu di loket DAPD Buleleng dengan membawa kartu identitas asli. Setelah petugas membuka kuncinya, Anda bisa mendaftar lagi.";
    }

    /** Pesan untuk petugas yang mencoba memakai nomor terkunci di formulir admin. */
    public static function lockedMessageForStaff(string $kind): string
    {
        $label = Member::IDENTITY_NUMBER_RULES[$kind]['label'] ?? 'Nomor identitas';

        return "{$label} ini terkunci karena akun lamanya dihapus saat masih ada kasus bermasalah. Buka kuncinya dulu di halaman Keranjang Bermasalah, bagian NIK terkunci.";
    }
}
