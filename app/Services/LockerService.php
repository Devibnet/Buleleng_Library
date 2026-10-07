<?php

namespace App\Services;

use App\Models\Locker;
use App\Models\LockerLoan;
use App\Models\Member;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LockerService
{
    /**
     * 1. PEMINJAMAN LOKER (borrowLocker)
     */
    public function borrowLocker(string $memberNo, string $lockerCode, ?string $depositType = 'KTP', ?int $operatorAdminId = null): LockerLoan
    {
        return DB::transaction(function () use ($memberNo, $lockerCode, $depositType, $operatorAdminId) {
            // Validasi Member
            $member = Member::where('member_no', $memberNo)
                ->orWhere('id', $memberNo)
                ->lockForUpdate()
                ->first();

            if (! $member) {
                throw new Exception("Anggota dengan nomor/ID '{$memberNo}' tidak ditemukan.");
            }

            // Cek peminjaman loker aktif member (BORROWED / ACTIVE)
            $activeLoan = LockerLoan::where('member_id', $member->id)
                ->whereIn('status', ['BORROWED', 'ACTIVE'])
                ->first();

            if ($activeLoan) {
                throw new Exception("Anggota '{$member->name}' masih memiliki peminjaman loker aktif (No. Peminjaman: {$activeLoan->loan_number}). Tolak transaksi baru.");
            }

            // Validasi Loker
            $locker = Locker::where('locker_code', $lockerCode)
                ->lockForUpdate()
                ->first();

            if (! $locker) {
                throw new Exception("Loker dengan kode '{$lockerCode}' tidak ditemukan.");
            }

            if ($locker->status !== 'AVAILABLE') {
                throw new Exception("Loker dengan kode '{$lockerCode}' sedang tidak tersedia (Status: {$locker->status}).");
            }

            // Eksekusi Transaksi
            $loan = LockerLoan::create([
                'loan_number'          => LockerLoan::generateLoanNumber(),
                'member_id'            => $member->id,
                'locker_id'            => $locker->id,
                'deposit_type'         => $depositType ?: 'KTP',
                'borrow_date'          => now(),
                'status'               => 'BORROWED',
                'processed_by_user_id' => $operatorAdminId,
            ]);

            // Update status loker menjadi OCCUPIED
            $locker->update(['status' => 'OCCUPIED']);

            return $loan;
        });
    }

    /**
     * 2. PENGEMBALIAN LOKER (returnLocker)
     */
    public function returnLocker(string $identifier, ?string $violationNote = null, ?int $operatorAdminId = null): LockerLoan
    {
        return DB::transaction(function () use ($identifier, $violationNote, $operatorAdminId) {
            // Cari peminjaman loker aktif berdasarkan kode loker atau nomor member
            $loan = LockerLoan::with(['locker', 'member'])
                ->whereIn('status', ['BORROWED', 'ACTIVE'])
                ->where(function ($q) use ($identifier) {
                    $q->whereHas('locker', function ($lq) use ($identifier) {
                        $lq->where('locker_code', $identifier);
                    })->orWhereHas('member', function ($mq) use ($identifier) {
                        $mq->where('member_no', $identifier)->orWhere('id', $identifier);
                    });
                })
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new Exception("Transaksi peminjaman loker aktif tidak ditemukan untuk kode loker / nomor member '{$identifier}'.");
            }

            $targetStatus = ! empty($violationNote) ? 'VIOLATION' : 'RETURNED';

            // Update record locker_loans
            $loan->update([
                'return_date'    => now(),
                'status'         => $targetStatus,
                'violation_note' => $violationNote,
            ]);

            // Update status loker kembali menjadi AVAILABLE
            if ($loan->locker) {
                $loan->locker->update(['status' => 'AVAILABLE']);
            }

            return $loan;
        });
    }

    /**
     * 3. RIWAYAT & MONITORING (getLoansHistory)
     */
    public function getLoansHistory(?string $status = null, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = LockerLoan::with(['member', 'locker', 'processedByAdmin']);

        if (! empty($status) && $status !== 'ALL') {
            $query->where('status', $status);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                  ->orWhereHas('member', function ($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%")
                        ->orWhere('member_no', 'like', "%{$search}%");
                  })
                  ->orWhereHas('locker', function ($lq) use ($search) {
                      $lq->where('locker_code', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }
}
