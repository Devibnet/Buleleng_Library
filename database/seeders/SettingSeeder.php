<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultSettings = [
            [
                'key'   => 'loan_duration_days',
                'value' => '7',
                'group' => 'circulation',
                'label' => 'Durasi Peminjaman (Hari)',
            ],
            [
                'key'   => 'max_borrow_qty',
                'value' => '3',
                'group' => 'circulation',
                'label' => 'Maksimal Buku Dipinjam',
            ],
            [
                'key'   => 'fine_per_day',
                'value' => '1000',
                'group' => 'circulation',
                'label' => 'Denda Keterlambatan Per Hari',
            ],
            [
                'key'   => 'terms_and_conditions',
                'value' => 'Syarat & Ketentuan Peminjaman Perpustakaan Daerah Kabupaten Buleleng: 1. Peminjam wajib terdaftar sebagai anggota aktif. 2. Batas maksimal peminjaman adalah 3 buku dengan durasi 7 hari. 3. Keterlambatan pengembalian dikenakan denda Rp 1.000,- per hari per buku.',
                'group' => 'general',
                'label' => 'Syarat & Ketentuan Peminjaman',
            ],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
