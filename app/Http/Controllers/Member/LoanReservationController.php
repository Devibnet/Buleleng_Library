<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReserveLoanRequest;
use App\Models\Catalog;
use App\Models\Loan;
use App\Services\LoanService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoanReservationController extends Controller
{
    protected LoanService $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    /**
     * Tampilkan halaman/form reservasi online bagi member
     */
    public function index(Request $request): View
    {
        $member = Auth::guard('member')->user();

        $myReservations = Loan::with(['collection.catalog'])
            ->where('member_id', $member->id)
            ->whereIn('status', [Loan::STATUS_RESERVED, Loan::STATUS_BORROWED])
            ->orderBy('id', 'desc')
            ->get();

        $catalogs = Catalog::where('available_copies', '>', 0)
            ->orderBy('title', 'asc')
            ->get();

        return view('member.reservation', compact('member', 'myReservations', 'catalogs'));
    }

    /**
     * Proses pembuatan reservasi online oleh member
     */
    public function store(ReserveLoanRequest $request): RedirectResponse
    {
        $member = Auth::guard('member')->user();

        try {
            $loan = $this->loanService->reserveOnline(
                $member,
                (int) $request->validated('catalog_id'),
                (int) ($request->validated('pickup_days') ?? 2)
            );

            return redirect()->back()->with('success', "Reservasi berhasil dibuat! Kode Reservasi Anda: {$loan->reservation_code}. Silakan ambil buku sebelum " . $loan->pickup_deadline->format('d/m/Y H:i'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
