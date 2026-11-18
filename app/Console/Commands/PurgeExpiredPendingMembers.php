<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus pendaftaran yang memilih verifikasi tatap muka tetapi tidak datang ke DAPD sebelum batas
 * waktunya (Member::COUNTER_VERIFICATION_DAYS hari). Prinsip minimisasi data UU PDP: data pendaftar
 * yang tidak pernah diverifikasi tidak disimpan tanpa batas.
 */
class PurgeExpiredPendingMembers extends Command
{
    protected $signature = 'members:purge-expired-pending {--dry-run : Tampilkan yang akan dihapus tanpa menghapus}';

    protected $description = 'Hapus pendaftaran anggota yang tidak diverifikasi tatap muka sampai batas waktunya';

    public function handle(): int
    {
        $expired = Member::pendingCounter()
            ->where('verification_expires_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Tidak ada pendaftaran kedaluwarsa.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($expired as $member) {
            $label = "#{$member->id} {$member->name} (daftar " . $member->created_at?->format('d/m/Y') . ')';

            // Pengaman: akun dengan riwayat peminjaman tidak pernah dihapus otomatis
            if ($member->loans()->exists()) {
                $this->warn("Dilewati (punya riwayat peminjaman): {$label}");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Akan dihapus: {$label}");

                continue;
            }

            $member->deleteRegistration();
            $deleted++;
            $this->line("Dihapus: {$label}");
        }

        $this->info($this->option('dry-run') ? 'Simulasi selesai, tidak ada data yang dihapus.' : "{$deleted} pendaftaran kedaluwarsa dihapus.");

        return self::SUCCESS;
    }
}
