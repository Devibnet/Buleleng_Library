<?php

namespace App\Support;

/**
 * Aturan password akun petugas (harus selaras dengan PASSWORD_RULES di frontend).
 * Minimal 6 karakter, 1 huruf besar, 1 angka, dan 1 karakter unik.
 */
class StaffPassword
{
    public const MIN_LENGTH = 6;

    /** @return array<int, string> */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'min:' . self::MIN_LENGTH,
            'regex:/[A-Z]/',
            'regex:/\d/',
            'regex:/[^A-Za-z0-9\s]/',
            'confirmed',
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal harus ' . self::MIN_LENGTH . ' karakter.',
            'password.confirmed' => 'Verifikasi password tidak cocok.',
            'password.regex'     => 'Password harus mengandung minimal 1 huruf besar, 1 angka, dan 1 karakter unik.',
        ];
    }
}
