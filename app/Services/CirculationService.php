<?php

namespace App\Services;

use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class CirculationService
{
    /**
     * Hitung pickup_deadline H+3 kerja (mengabaikan Sabtu dan Minggu)
     */
    public function calculatePickupDeadline(?Carbon $now = null): Carbon
    {
        $date = ($now ? $now->copy() : now());
        $workingDaysAdded = 0;

        while ($workingDaysAdded < 3) {
            $date->addDay();
            if (! $date->isWeekend()) {
                $workingDaysAdded++;
            }
        }

        return $date->endOfDay();
    }

    /**
     * 1. FUNGSI MEMBER: Reservasi Buku Online (reserveBook)
     * - Status Awal: PENDING_CHECK
     * - Validasi kuota member & stok katalog
     * - Stok available_copies LANGSUNG berkurang (-1)
     * - pickup_deadline BELUM dihitung (dihitung setelah petugas konfirmasi READY_FOR_PICKUP)
     */
    public function reserveBook(int $memberId, int $bookId, ?int $operatorAdminId = null): Loan
    {
        return DB::transaction(function () use ($memberId, $bookId, $operatorAdminId) {
            $member = Member::where('id', $memberId)->lockForUpdate()->firstOrFail();

            // Validasi Kuota Member: Count('PENDING_CHECK' + 'READY_FOR_PICKUP' + 'RESERVED' + 'BORROWED') < max_borrow_qty
            $maxBorrowQty = (int) get_setting('max_borrow_qty', 3);
            $activeCount = Loan::where('member_id', $member->id)
                ->whereIn('status', [
                    Loan::STATUS_PENDING_CHECK,
                    Loan::STATUS_READY_FOR_PICKUP,
                    Loan::STATUS_RESERVED,
                    Loan::STATUS_BORROWED,
                    Loan::STATUS_OVERDUE
                ])
                ->count();

            if ($activeCount >= $maxBorrowQty) {
                throw new Exception("Batas maksimal peminjaman dan reservasi aktif adalah {$maxBorrowQty} buku. Saat ini Anda memiliki {$activeCount} transaksi aktif.");
            }

            // Validasi stok catalog
            $catalog = Catalog::where('id', $bookId)->lockForUpdate()->firstOrFail();

            if ($catalog->available_copies <= 0) {
                throw new Exception("Stok ketersediaan buku '{$catalog->title}' sedang kosong.");
            }

            // Cari eksemplar koleksi fisik AVAILABLE
            $collection = Collection::where('catalog_id', $catalog->id)
                ->where('availability_status', 'AVAILABLE')
                ->where('is_quarantine', false)
                ->where(function ($q) {
                    $q->whereNull('physical_condition')
                      ->orWhereNotIn('physical_condition', ['Rusak', 'Rusak Ringan', 'Rusak Berat', 'Hilang']);
                })
                ->lockForUpdate()
                ->first();

            if (! $collection) {
                throw new Exception("Eksemplar fisik buku tidak tersedia untuk reservasi saat ini.");
            }

            // Set status ketersediaan koleksi menjadi PENDING_CHECK
            $collection->update(['availability_status' => 'PENDING_CHECK']);
            $catalog->recalculateCopies();

            // Buat record loans dengan status PENDING_CHECK (pickup_deadline = null)
            $loan = Loan::create([
                'loan_number'        => Loan::generateLoanNumber(),
                'reservation_code'   => Loan::generateReservationCode(),
                'member_id'          => $member->id,
                'collection_id'      => $collection->id,
                'status'             => Loan::STATUS_PENDING_CHECK,
                'pickup_deadline'    => null,
                'created_by_user_id' => $operatorAdminId,
            ]);

            return $loan;
        });
    }

    /**
     * 2. DASHBOARD OPERATOR: Get daftar reservasi pending yang perlu diambil di rak
     */
    public function getPendingReservations()
    {
        return Loan::with(['member', 'collection.catalog', 'createdByAdmin'])
            ->where('status', Loan::STATUS_PENDING_CHECK)
            ->latest()
            ->get();
    }

    /**
     * 3. DASHBOARD OPERATOR: Konfirmasi Fisik Buku Ada (confirmReady)
     * - Diubah ke status READY_FOR_PICKUP
     * - pickup_deadline (H+3 hari kerja) MULAI DIHITUNG dari titik ini
     */
    public function confirmReady($loanId, ?int $operatorAdminId = null): Loan
    {
        return DB::transaction(function () use ($loanId, $operatorAdminId) {
            $loan = Loan::where(fn ($q) => $q->where('id', $loanId)->orWhere('reservation_code', $loanId))
                ->where('status', Loan::STATUS_PENDING_CHECK)
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new Exception("Transaksi reservasi pending dengan ID/Kode '{$loanId}' tidak ditemukan.");
            }

            $pickupDeadline = $this->calculatePickupDeadline(now());

            $loan->update([
                'status'             => Loan::STATUS_READY_FOR_PICKUP,
                'pickup_deadline'    => $pickupDeadline,
                'created_by_user_id' => $operatorAdminId ?? $loan->created_by_user_id,
            ]);

            if ($loan->collection) {
                $loan->collection->update(['availability_status' => 'READY_FOR_PICKUP']);
            }

            return $loan;
        });
    }

    /**
     * 4. DASHBOARD OPERATOR: Tandai Buku Tidak Ditemukan / Hilang di Rak (markUnavailable)
     * - Diubah ke status UNAVAILABLE
     * - Mengembalikan stok available_copies (+1) dan membatalkan reservasi
     */
    public function markUnavailable($loanId, ?string $reason = null, ?int $operatorAdminId = null): Loan
    {
        return DB::transaction(function () use ($loanId, $operatorAdminId) {
            $loan = Loan::where(fn ($q) => $q->where('id', $loanId)->orWhere('reservation_code', $loanId))
                ->whereIn('status', [Loan::STATUS_PENDING_CHECK, Loan::STATUS_READY_FOR_PICKUP])
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new Exception("Transaksi reservasi dengan ID/Kode '{$loanId}' tidak ditemukan.");
            }

            $loan->update([
                'status'             => Loan::STATUS_UNAVAILABLE,
                'created_by_user_id' => $operatorAdminId ?? $loan->created_by_user_id,
            ]);

            if ($loan->collection) {
                $loan->collection->update(['availability_status' => 'AVAILABLE']);
                if ($loan->collection->catalog) {
                    $loan->collection->catalog->recalculateCopies();
                }
            }

            return $loan;
        });
    }

    /**
     * 2a. FUNGSI OPERATOR: Peminjaman Langsung di Loket (processDirectBorrow)
     * - Validasi kuota & stok
     * - Buat record status = 'BORROWED', borrow_date = now, due_date = +7 hari
     * - Kurangi available_copies (-1)
     */
    public function processDirectBorrow(string $memberNo, string $bookBarcode, ?int $operatorAdminId = null, int $durationDays = 7): Loan
    {
        return DB::transaction(function () use ($memberNo, $bookBarcode, $operatorAdminId, $durationDays) {
            $member = Member::where('member_no', $memberNo)
                ->orWhere('id', $memberNo)
                ->lockForUpdate()
                ->first();

            if (! $member) {
                throw new Exception("Anggota dengan nomor/ID '{$memberNo}' tidak ditemukan.");
            }

            // Validasi Kuota Member: reservasi aktif + pinjaman aktif < max_borrow_qty
            $maxBorrowQty = (int) get_setting('max_borrow_qty', 3);
            $activeCount = Loan::where('member_id', $member->id)
                ->whereIn('status', [
                    Loan::STATUS_PENDING_CHECK,
                    Loan::STATUS_READY_FOR_PICKUP,
                    Loan::STATUS_RESERVED,
                    Loan::STATUS_BORROWED,
                    Loan::STATUS_OVERDUE,
                ])
                ->count();

            if ($activeCount >= $maxBorrowQty) {
                throw new Exception("Batas maksimal peminjaman dan reservasi aktif adalah {$maxBorrowQty} buku. Anggota {$member->name} saat ini memiliki {$activeCount} transaksi aktif.");
            }

            // Cari koleksi fisik berdasarkan barcode
            $collection = Collection::where('barcode_no', $bookBarcode)
                ->lockForUpdate()
                ->first();

            if (! $collection) {
                throw new Exception("Buku dengan barcode '{$bookBarcode}' tidak ditemukan.");
            }

            if ($collection->is_quarantine) {
                throw new Exception("Buku ini sedang dalam masa karantina.");
            }

            if ($collection->availability_status !== 'AVAILABLE') {
                throw new Exception("Buku ini sedang tidak tersedia (Status: {$collection->availability_status}).");
            }

            if (in_array(strtolower($collection->physical_condition ?? 'baik'), ['rusak', 'rusak ringan', 'rusak berat', 'hilang'])) {
                throw new Exception("Eksemplar buku ini tidak dapat dipinjam karena kondisi fisik: {$collection->physical_condition}.");
            }

            $catalog = Catalog::where('id', $collection->catalog_id)->lockForUpdate()->firstOrFail();

            if ($catalog->available_copies <= 0) {
                throw new Exception("Stok ketersediaan katalog buku '{$catalog->title}' sudah habis.");
            }

            // Set status ketersediaan koleksi menjadi BORROWED
            $collection->update(['availability_status' => 'BORROWED']);
            $catalog->recalculateCopies();

            // Buat record status = BORROWED, borrow_date = now, due_date = +loan_duration_days
            $loanDays = $durationDays ?: (int) get_setting('loan_duration_days', 7);
            $loan = Loan::create([
                'loan_number'        => Loan::generateLoanNumber(),
                'member_id'          => $member->id,
                'collection_id'      => $collection->id,
                'borrow_date'        => now(),
                'loan_date'          => now()->toDateString(),
                'due_date'           => now()->addDays($loanDays)->toDateString(),
                'status'             => Loan::STATUS_BORROWED,
                'created_by_user_id' => $operatorAdminId,
            ]);

            return $loan;
        });
    }

    /**
     * 2b. FUNGSI OPERATOR: Konfirmasi Pengambilan Reservasi (confirmPickup)
     * - Cari transaksi status == 'RESERVED'
     * - Ubah status = 'BORROWED', borrow_date = now, due_date = +7 hari
     * - Stok TIDAK berkurang lagi (karena sudah berkurang saat reservasi)
     */
    public function confirmPickup(string $reservationCode, ?int $operatorAdminId = null, int $durationDays = 7): Loan
    {
        return DB::transaction(function () use ($reservationCode, $operatorAdminId, $durationDays) {
            $loan = Loan::where('reservation_code', $reservationCode)
                ->whereIn('status', [Loan::STATUS_READY_FOR_PICKUP, Loan::STATUS_RESERVED, Loan::STATUS_PENDING_CHECK])
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new Exception("Kode reservasi '{$reservationCode}' tidak ditemukan atau statusnya tidak aktif.");
            }

            // Cek jika sudah melewati pickup_deadline
            if ($loan->pickup_deadline && now()->greaterThan($loan->pickup_deadline)) {
                $this->cancelExpiredLoan($loan);
                throw new Exception("Reservasi '{$reservationCode}' telah melewati batas waktu tenggat pengambilan (EXPIRED).");
            }

            $loanDays = $durationDays ?: (int) get_setting('loan_duration_days', 7);

            // Ubah status = BORROWED, borrow_date = now, due_date = +loan_duration_days
            $loan->update([
                'status'             => Loan::STATUS_BORROWED,
                'borrow_date'        => now(),
                'loan_date'          => now()->toDateString(),
                'due_date'           => now()->addDays($loanDays)->toDateString(),
                'created_by_user_id' => $operatorAdminId ?? $loan->created_by_user_id,
            ]);

            if ($loan->collection) {
                $loan->collection->update(['availability_status' => 'BORROWED']);
                if ($loan->collection->catalog) {
                    $loan->collection->catalog->recalculateCopies();
                }
            }

            return $loan;
        });
    }

    /**
     * 2c. FUNGSI OPERATOR: Pengembalian Buku (processReturn)
     * - Cari transaksi status == 'BORROWED' / 'OVERDUE'
     * - Hitung denda keterlambatan jika now() > due_date
     * - Ubah status = 'RETURNED', return_date = now
     * - Kelola kondisi fisik dan status ketersediaan eksemplar (jika rusak -> karantina)
     */
    public function processReturn(
        string $memberNo,
        string $bookBarcode,
        ?int $operatorAdminId = null,
        ?string $condition = 'Baik',
        ?string $quarantineReason = null,
        ?string $notes = null
    ): Loan {
        return DB::transaction(function () use ($memberNo, $bookBarcode, $operatorAdminId, $condition, $quarantineReason, $notes) {
            $member = Member::where('member_no', $memberNo)
                ->orWhere('id', $memberNo)
                ->first();

            if (! $member) {
                throw new Exception("Anggota dengan nomor/ID '{$memberNo}' tidak ditemukan.");
            }

            $collection = Collection::where('barcode_no', $bookBarcode)->first();

            if (! $collection) {
                throw new Exception("Buku dengan barcode '{$bookBarcode}' tidak ditemukan.");
            }

            $loan = Loan::where('member_id', $member->id)
                ->where('collection_id', $collection->id)
                ->whereIn('status', [Loan::STATUS_BORROWED, Loan::STATUS_OVERDUE])
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new Exception("Transaksi peminjaman aktif tidak ditemukan untuk anggota '{$member->name}' dan barcode '{$bookBarcode}'.");
            }

            $today = now()->startOfDay();
            $dueDate = $loan->due_date ? $loan->due_date->startOfDay() : $today;
            $lateDays = 0;
            $fineAmount = 0;

            if ($today->greaterThan($dueDate)) {
                $lateDays = (int) $dueDate->diffInDays($today);
                $fineRate = (float) get_setting('fine_per_day', 1000);
                $fineAmount = $lateDays * $fineRate;
            }

            // Ubah status loan = RETURNED, set return_date = now
            $loan->update([
                'status'              => Loan::STATUS_RETURNED,
                'return_date'         => now()->toDateString(),
                'late_days'           => $lateDays,
                'fine_amount'         => $fineAmount,
                'returned_by_user_id' => $operatorAdminId,
            ]);

            $cond = $condition ?: 'Baik';
            $isDamaged = in_array(strtolower($cond), ['rusak', 'rusak ringan', 'rusak berat', 'hilang', 'damaged']);

            if ($isDamaged) {
                // Buku rusak: hanya eksemplar ini yang masuk karantina!
                $collection->update([
                    'availability_status' => 'QUARANTINED',
                    'physical_condition'  => $cond,
                    'is_quarantine'       => true,
                    'quarantine_reason'   => $quarantineReason ?: 'Buku dikembalikan dalam kondisi ' . $cond,
                    'quarantine_notes'    => $notes,
                ]);
            } else {
                // Buku kondisi baik: status ketersediaan kembali AVAILABLE
                $collection->update([
                    'availability_status' => 'AVAILABLE',
                    'physical_condition'  => 'Baik',
                    'is_quarantine'       => false,
                    'quarantine_reason'   => null,
                    'quarantine_notes'    => null,
                ]);
            }

            // Hitung ulang ketersediaan stok fisik katalog
            if ($collection->catalog) {
                $collection->catalog->recalculateCopies();
            }

            return $loan;
        });
    }

    /**
     * 2d. FUNGSI OPERATOR: Perpanjangan Masa Pinjam (processRenewal)
     * - Cari transaksi status == 'BORROWED'
     * - Validasi: belum lewat due_date, renewal_count < 2
     * - Tambahkan due_date (+7 hari), increment renewal_count / extension_count
     */
    public function processRenewal(string $bookBarcode, int $extensionDays = 7): Loan
    {
        return DB::transaction(function () use ($bookBarcode, $extensionDays) {
            $collection = Collection::where('barcode_no', $bookBarcode)->first();

            if (! $collection) {
                throw new Exception("Buku dengan barcode '{$bookBarcode}' tidak ditemukan.");
            }

            $loan = Loan::where('collection_id', $collection->id)
                ->where('status', Loan::STATUS_BORROWED)
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new Exception("Transaksi peminjaman aktif tidak ditemukan untuk buku dengan barcode '{$bookBarcode}'.");
            }

            // Validasi 1: Belum lewat due_date
            $today = now()->startOfDay();
            $dueDate = $loan->due_date ? $loan->due_date->startOfDay() : $today;

            if ($today->greaterThan($dueDate)) {
                throw new Exception("Peminjaman yang sudah terlambat tidak dapat diperpanjang.");
            }

            // Validasi 2: renewal_count < 2
            if ($loan->extension_count >= 2) {
                throw new Exception("Batas maksimal perpanjangan adalah 2 kali.");
            }

            // Tambahkan due_date (+7 hari), increment extension_count
            $newDueDate = $dueDate->copy()->addDays($extensionDays)->toDateString();

            $loan->update([
                'due_date'        => $newDueDate,
                'extension_count' => $loan->extension_count + 1,
            ]);

            return $loan;
        });
    }

    /**
     * 3. AUTOMATION: Pembatalan Reservasi Expired (handleExpiredReservations)
     * - Cari transaksi RESERVED dengan pickup_deadline < now()
     * - Ubah status = 'EXPIRED'
     * - Tambahkan available_copies (+1)
     */
    public function handleExpiredReservations(): int
    {
        $expiredLoans = Loan::whereIn('status', [Loan::STATUS_RESERVED, Loan::STATUS_READY_FOR_PICKUP])
            ->whereNotNull('pickup_deadline')
            ->where('pickup_deadline', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredLoans as $loan) {
            DB::transaction(function () use ($loan) {
                $this->cancelExpiredLoan($loan);
            });
            $count++;
        }

        return $count;
    }

    /**
     * FUNGSI MEMBER: Batalkan reservasi milik sendiri (PENDING_CHECK / READY_FOR_PICKUP)
     * - Status menjadi CANCELLED dan eksemplar dikembalikan ke rak (AVAILABLE)
     */
    public function cancelReservation(int $memberId, int $loanId): Loan
    {
        return DB::transaction(function () use ($memberId, $loanId) {
            $loan = Loan::where('id', $loanId)
                ->where('member_id', $memberId)
                ->whereNotNull('reservation_code')
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new Exception('Reservasi tidak ditemukan.');
            }

            if (! in_array($loan->status, [Loan::STATUS_PENDING_CHECK, Loan::STATUS_READY_FOR_PICKUP], true)) {
                throw new Exception("Reservasi {$loan->reservation_code} tidak dapat dibatalkan (Status: {$loan->status}).");
            }

            $loan->update(['status' => Loan::STATUS_CANCELLED]);
            $this->releaseReservedCollection($loan);

            return $loan;
        });
    }

    /**
     * Kembalikan eksemplar ke rak (AVAILABLE) hanya jika eksemplar masih "dipegang" oleh reservasi.
     * Eksemplar yang sudah dipinjam transaksi lain atau masuk karantina tidak boleh ikut berubah.
     */
    protected function releaseReservedCollection(Loan $loan): void
    {
        $collection = $loan->collection;
        if (! $collection) {
            return;
        }

        if (in_array($collection->availability_status, ['PENDING_CHECK', 'READY_FOR_PICKUP', 'RESERVED'], true)) {
            $collection->update(['availability_status' => 'AVAILABLE']);
        }

        $collection->catalog?->recalculateCopies();
    }

    /**
     * Helper internal untuk membatalkan reservasi expired dan merestorasi stok
     */
    protected function cancelExpiredLoan(Loan $loan): void
    {
        $loan->update(['status' => Loan::STATUS_EXPIRED]);
        $this->releaseReservedCollection($loan);
    }
}
