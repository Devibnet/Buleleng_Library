<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmReservationRequest;
use App\Http\Requests\DirectLoanRequest;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CirculationController extends Controller
{
    protected LoanService $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    /**
     * Dashboard & Form Peminjaman/Sirkulasi Loket Operator
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'ALL');
        $search = $request->query('search');

        $query = Loan::with(['member', 'collection.catalog', 'createdByAdmin']);

        if ($status !== 'ALL') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                  ->orWhere('reservation_code', 'like', "%{$search}%")
                  ->orWhereHas('member', function ($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%")
                        ->orWhere('member_no', 'like', "%{$search}%");
                  })
                  ->orWhereHas('collection', function ($cq) use ($search) {
                      $cq->where('barcode_no', 'like', "%{$search}%");
                  });
            });
        }

        $loans = $query->orderBy('id', 'desc')->paginate(15);
        $members = Member::orderBy('name', 'asc')->get();

        // Jika ada pencarian spesifik kode reservasi untuk dikonfirmasi
        $reservationResult = null;
        if ($request->has('search_reservation_code')) {
            $code = trim($request->input('search_reservation_code'));
            $reservationResult = Loan::with(['member', 'collection.catalog'])
                ->where('reservation_code', $code)
                ->where('status', Loan::STATUS_RESERVED)
                ->first();
        }

        return view('admin.circulation.index', compact('loans', 'members', 'reservationResult', 'status', 'search'));
    }

    /**
     * Jalur Online (Step 2): Konfirmasi Pengambilan Reservasi di Loket Operator
     */
    public function confirmReservation(ConfirmReservationRequest $request): RedirectResponse
    {
        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $loan = $this->loanService->confirmReservation(
                $request->validated('reservation_code'),
                $operatorAdmin->id,
                (int) ($request->validated('duration_days') ?? 7)
            );

            return redirect()->route('admin.circulation.loans.index')
                ->with('success', "Reservasi {$loan->reservation_code} berhasil dikonfirmasi! Buku resmi dipinjam (No. Peminjaman: {$loan->loan_number}). Tanggal Jatuh Tempo: " . $loan->due_date->format('d/m/Y'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Jalur Offline: Peminjaman Langsung di Loket Operator (Direct Counter)
     */
    public function storeDirect(DirectLoanRequest $request): RedirectResponse
    {
        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $loan = $this->loanService->borrowDirect(
                (int) $request->validated('member_id'),
                $request->validated('barcode_no'),
                $operatorAdmin->id,
                (int) ($request->validated('duration_days') ?? 7)
            );

            return redirect()->route('admin.circulation.loans.index')
                ->with('success', "Peminjaman langsung berhasil dicatat! No. Peminjaman: {$loan->loan_number}. Jatuh tempo pada " . $loan->due_date->format('d/m/Y'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Pengembalian Buku di Loket Operator
     */
    public function returnLoan(Request $request, Loan $loan): RedirectResponse
    {
        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $updatedLoan = $this->loanService->returnBook($loan->id, $operatorAdmin->id);

            $msg = "Buku untuk transaksi {$updatedLoan->loan_number} telah berhasil dikembalikan.";
            if ($updatedLoan->late_days > 0) {
                $msg .= " Terlambat {$updatedLoan->late_days} hari. Denda: Rp " . number_format($updatedLoan->fine_amount, 0, ',', '.');
            }

            return redirect()->back()->with('success', $msg);
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
