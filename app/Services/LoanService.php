<?php

namespace App\Services;

use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
use Exception;
use Illuminate\Support\Facades\DB;

class LoanService
{
    /**
     * 1. JALUR ONLINE: Member reservasi buku via website
     */
    public function reserveOnline(Member $member, int $catalogId, int $pickupDays = 2): Loan
    {
        return DB::transaction(function () use ($member, $catalogId, $pickupDays) {
            // Cek catalog & stok
            $catalog = Catalog::where('id', $catalogId)->lockForUpdate()->firstOrFail();

            if ($catalog->available_copies <= 0) {
                throw new Exception("Stok eksemplar buku '{$catalog->title}' sedang kosong / tidak tersedia.");
            }

            // Cari eksemplar koleksi fisik yang statusnya AVAILABLE
            $collection = Collection::where('catalog_id', $catalogId)
                ->where('availability_status', 'AVAILABLE')
                ->where('is_quarantine', false)
                ->lockForUpdate()
                ->first();

            if (! $collection) {
                throw new Exception("Eksemplar fisik buku tidak tersedia untuk dipesan saat ini.");
            }

            // Kurangi stok available_copies (-1)
            $catalog->decrement('available_copies');

            // Ubah status ketersediaan koleksi menjadi RESERVED
            $collection->update(['availability_status' => 'RESERVED']);

            // Buat transaksi loan status RESERVED
            $loan = Loan::create([
                'loan_number'      => Loan::generateLoanNumber(),
                'reservation_code' => Loan::generateReservationCode(),
                'member_id'        => $member->id,
                'collection_id'    => $collection->id,
                'status'           => Loan::STATUS_RESERVED,
                'pickup_deadline'  => now()->addDays($pickupDays),
            ]);

            return $loan;
        });
    }

    /**
     * 1b. JALUR ONLINE: Operator konfirmasi pengambilan reservasi di loket
     */
    public function confirmReservation(string $reservationCode, int $operatorAdminId, int $durationDays = 7): Loan
    {
        return DB::transaction(function () use ($reservationCode, $operatorAdminId, $durationDays) {
            $loan = Loan::where('reservation_code', $reservationCode)
                ->where('status', Loan::STATUS_RESERVED)
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new Exception("Kode reservasi '{$reservationCode}' tidak ditemukan atau statusnya sudah tidak aktif.");
            }

            // Cek jika sudah lewat deadline
            if (now()->greaterThan($loan->pickup_deadline)) {
                $this->cancelReservationLoan($loan, Loan::STATUS_EXPIRED);
                throw new Exception("Reservasi '{$reservationCode}' telah kadaluarsa (melewati batas waktu pengambilan).");
            }

            // Ubah status loan menjadi BORROWED
            $loan->update([
                'status'             => Loan::STATUS_BORROWED,
                'borrow_date'        => now(),
                'loan_date'          => now()->toDateString(),
                'due_date'           => now()->addDays($durationDays)->toDateString(),
                'created_by_user_id' => $operatorAdminId,
            ]);

            // Ubah status koleksi menjadi BORROWED
            $loan->collection->update(['availability_status' => 'BORROWED']);

            return $loan;
        });
    }

    /**
     * 2. JALUR OFFLINE: Operator peminjaman langsung di loket
     */
    public function borrowDirect(int $memberId, string $barcodeNo, int $operatorAdminId, int $durationDays = 7): Loan
    {
        return DB::transaction(function () use ($memberId, $barcodeNo, $operatorAdminId, $durationDays) {
            $member = Member::findOrFail($memberId);

            // Scan / Cari koleksi berdasarkan barcode
            $collection = Collection::where('barcode_no', $barcodeNo)
                ->lockForUpdate()
                ->first();

            if (! $collection) {
                throw new Exception("Buku dengan barcode '{$barcodeNo}' tidak ditemukan.");
            }

            if ($collection->is_quarantine) {
                throw new Exception("Buku ini sedang dalam masa karantina.");
            }

            if ($collection->availability_status !== 'AVAILABLE') {
                throw new Exception("Buku ini sedang tidak tersedia (Status: {$collection->availability_status}).");
            }

            $catalog = Catalog::where('id', $collection->catalog_id)->lockForUpdate()->firstOrFail();

            if ($catalog->available_copies <= 0) {
                throw new Exception("Stok ketersediaan katalog buku '{$catalog->title}' sudah habis.");
            }

            // Kurangi stok available_copies (-1)
            $catalog->decrement('available_copies');

            // Ubah status ketersediaan koleksi menjadi BORROWED
            $collection->update(['availability_status' => 'BORROWED']);

            // Buat transaksi peminjaman langsung (BORROWED)
            $loan = Loan::create([
                'loan_number'        => Loan::generateLoanNumber(),
                'member_id'          => $member->id,
                'collection_id'      => $collection->id,
                'borrow_date'        => now(),
                'loan_date'          => now()->toDateString(),
                'due_date'           => now()->addDays($durationDays)->toDateString(),
                'status'             => Loan::STATUS_BORROWED,
                'created_by_user_id' => $operatorAdminId,
            ]);

            return $loan;
        });
    }

    /**
     * Pengembalian Buku
     */
    public function returnBook(int $loanId, int $operatorAdminId): Loan
    {
        return DB::transaction(function () use ($loanId, $operatorAdminId) {
            $loan = Loan::where('id', $loanId)
                ->whereIn('status', [Loan::STATUS_BORROWED, Loan::STATUS_OVERDUE])
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new Exception("Transaksi peminjaman tidak ditemukan atau buku sudah dikembalikan.");
            }

            $today = now()->startOfDay();
            $dueDate = $loan->due_date ? $loan->due_date->startOfDay() : $today;
            $lateDays = 0;
            $fineAmount = 0;

            if ($today->greaterThan($dueDate)) {
                $lateDays = (int) $today->diffInDays($dueDate);
                $fineAmount = $lateDays * 1000;
            }

            $loan->update([
                'status'              => Loan::STATUS_RETURNED,
                'return_date'         => now()->toDateString(),
                'late_days'           => $lateDays,
                'fine_amount'         => $fineAmount,
                'returned_by_user_id' => $operatorAdminId,
            ]);

            // Kembalikan status koleksi menjadi AVAILABLE
            $loan->collection->update(['availability_status' => 'AVAILABLE']);

            // Tambahkan stok catalog (+1)
            $loan->collection->catalog->increment('available_copies');

            return $loan;
        });
    }

    /**
     * 3. LOGIKA OTOMATIS: Membatalkan reservasi RESERVED yang melewati pickup_deadline
     */
    public function cancelExpiredReservations(): int
    {
        $expiredLoans = Loan::where('status', Loan::STATUS_RESERVED)
            ->where('pickup_deadline', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredLoans as $loan) {
            DB::transaction(function () use ($loan) {
                $this->cancelReservationLoan($loan, Loan::STATUS_EXPIRED);
            });
            $count++;
        }

        return $count;
    }

    /**
     * Helper internal untuk membatalkan reservasi dan merestorasi stok
     */
    protected function cancelReservationLoan(Loan $loan, string $targetStatus = Loan::STATUS_EXPIRED): void
    {
        $loan->update(['status' => $targetStatus]);

        if ($loan->collection) {
            $loan->collection->update(['availability_status' => 'AVAILABLE']);

            if ($loan->collection->catalog) {
                $loan->collection->catalog->increment('available_copies');
            }
        }
    }
}
