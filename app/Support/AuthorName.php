<?php

namespace App\Support;

/**
 * Pembersih nama pengarang sebelum disimpan ke katalog.
 *
 * Data sumber DAPD memakai format "Nama Belakang, Nama Depan". Bila salah satu bagian kosong,
 * yang tersisa adalah koma di depan (", Daryanto") atau di belakang ("Sukarno,").
 * Koma, titik koma, dan spasi di awal/akhir dibuang; isi yang tinggal tanda baca menjadi null.
 * Koma di tengah ("Sudarma, I Made") tetap dipertahankan karena itu format nama yang sah.
 */
class AuthorName
{
    public const FIELDS = [
        'author_main',
        'author_corporate',
        'author_additional_person',
        'author_additional_corporate',
    ];

    public static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', $value);
        $value = preg_replace('/^[\s,;]+|[\s,;]+$/u', '', $value);

        return $value === '' ? null : $value;
    }

    /**
     * Bersihkan semua kolom pengarang yang ada di array input (dipakai Form Request).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    public static function cleanFields(array $input): array
    {
        $cleaned = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input) && (is_string($input[$field]) || $input[$field] === null)) {
                $cleaned[$field] = self::clean($input[$field]);
            }
        }

        return $cleaned;
    }
}
