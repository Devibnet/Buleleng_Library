<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReserveLoanRequest;
use App\Http\Resources\CatalogResource;
use App\Http\Resources\LoanResource;
use App\Models\Catalog;
use App\Models\Loan;
use App\Services\LoanService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberLoanReservationController extends Controller
{
    protected LoanService $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    /**
     * GET /api/member/reservations
     * List katalog tersedia dan daftar reservasi aktif milik member
     */
    public function index(Request $request): JsonResponse
    {
        $member = Auth::guard('member')->user();

        $myReservations = Loan::with(['collection.catalog', 'member'])
            ->where('member_id', $member->id)
            ->whereIn('status', [Loan::STATUS_RESERVED, Loan::STATUS_BORROWED])
            ->orderBy('id', 'desc')
            ->get();

        $availableCatalogs = Catalog::where('available_copies', '>', 0)
            ->orderBy('title', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data reservasi member berhasil diambil.',
            'data'    => [
                'my_reservations'   => LoanResource::collection($myReservations),
                'available_catalogs'=> CatalogResource::collection($availableCatalogs),
            ],
        ], 200);
    }

    /**
     * POST /api/member/reservations
     * Buat reservasi buku online baru oleh member
     */
    public function store(ReserveLoanRequest $request): JsonResponse
    {
        $member = Auth::guard('member')->user();

        try {
            $loan = $this->loanService->reserveOnline(
                $member,
                (int) $request->validated('catalog_id'),
                (int) ($request->validated('pickup_days') ?? 2)
            );

            $loan->load(['collection.catalog', 'member']);

            return response()->json([
                'success' => true,
                'message' => "Reservasi berhasil dibuat! Kode Reservasi Anda: {$loan->reservation_code}. Silakan ambil buku di loket sebelum " . $loan->pickup_deadline->format('Y-m-d H:i:s'),
                'data'    => new LoanResource($loan),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
