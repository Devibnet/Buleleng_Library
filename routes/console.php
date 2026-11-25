<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Batalkan reservasi yang melewati batas pengambilan (H+3 hari kerja) & kembalikan stok
Schedule::command('loans:cancel-expired')->hourly();

// Hapus pendaftaran yang memilih verifikasi tatap muka tetapi tidak datang ke DAPD sampai batas waktunya
Schedule::command('members:purge-expired-pending')->dailyAt('01:30');

// Kirim email pengingat tenggat pengembalian buku (H-1 / hari ini) dan pemberitahuan keterlambatan tanpa denda setiap pagi
Schedule::command('loans:send-due-reminders')->dailyAt('08:00');
