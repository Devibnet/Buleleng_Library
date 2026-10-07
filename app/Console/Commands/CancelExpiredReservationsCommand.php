<?php

namespace App\Console\Commands;

use App\Services\CirculationService;
use Illuminate\Console\Command;

class CancelExpiredReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:cancel-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membatalkan reservasi peminjaman online (RESERVED) yang melewati batas waktu pickup_deadline dan merestorasi stok';

    /**
     * Execute the console command.
     */
    public function handle(CirculationService $circulationService): int
    {
        $this->info('Memulai pengecekan reservasi kadaluarsa...');

        $count = $circulationService->handleExpiredReservations();

        $this->info("Berhasil membatalkan {$count} reservasi yang kadaluarsa dan stok eksemplar telah dikembalikan.");

        return Command::SUCCESS;
    }
}
