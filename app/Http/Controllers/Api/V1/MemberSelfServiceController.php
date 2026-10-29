<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\Loan;
use App\Models\Member;
use App\Services\CirculationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Layanan Mandiri Anggota: Reservasi Saya & Pinjaman Saya (SPA, guard token Sanctum).
 */
class MemberSelfServiceController extends Controller
{
    private const QUOTA_STATUSES = [
        Loan::STATUS_PENDING_CHECK,
        Loan::STATUS_READY_FOR_PICKUP,
        Loan::STATUS_RESERVED,
        Loan::STATUS_BORROWED,
        Loan::STATUS_OVERDUE,
    ];

    private const RESERVATION_ACTIVE = [Loan::STATUS_PENDING_CHECK, Loan::STATUS_READY_FOR_PICKUP];

    private const LOAN_ACTIVE = [Loan::STATUS_BORROWED, Loan::STATUS_OVERDUE];

    public function __construct(protected CirculationService $circulationService)
    {
    }

    /**
     * GET /api/v1/member/reservations
     */
    public function reservations(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $this->circulationService->handleExpiredReservations();

        $reservations = Loan::with('collection.catalog')
            ->where('member_id', $member->id)
            ->whereNotNull('reservation_code')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar reservasi berhasil diambil.',
            'data'    => [
                'eligibility'  => $this->eligibility($member),
                'reservations' => $reservations->map(fn ($loan) => $this->reservationPayload($loan))->values(),
            ],
        ]);
    }

    /**
     * POST /api/v1/member/reservations
     * Body: { catalog_id }
     */
    public function reserve(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $validated = $request->validate(['catalog_id' => 'required|integer|exists:catalogs,id'], [
            'catalog_id.exists' => 'Buku yang dipilih tidak ditemukan di katalog.',
        ]);

        $this->circulationService->handleExpiredReservations();

        $eligibility = $this->eligibility($member);
        if (! $eligibility['eligible']) {
            return response()->json([
                'success' => false,
                'message' => $eligibility['block_reason'],
            ], 422);
        }

        $catalogId = (int) $validated['catalog_id'];
        $duplicate = Loan::where('member_id', $member->id)
            ->whereIn('status', [...self::RESERVATION_ACTIVE, ...self::LOAN_ACTIVE])
            ->whereHas('collection', fn ($q) => $q->where('catalog_id', $catalogId))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki reservasi atau pinjaman aktif untuk judul buku ini.',
            ], 422);
        }

        try {
            $loan = $this->circulationService->reserveBook($member->id, $catalogId);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $loan->load('collection.catalog');

        return response()->json([
            'success' => true,
            'message' => "Reservasi berhasil! Kode reservasi Anda: {$loan->reservation_code}. Petugas akan memverifikasi ketersediaan buku di rak.",
            'data'    => $this->reservationPayload($loan),
        ], 201);
    }

    /**
     * POST /api/v1/member/reservations/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $member = $this->member($request);

        try {
            $loan = $this->circulationService->cancelReservation($member->id, $id);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $loan->load('collection.catalog');

        return response()->json([
            'success' => true,
            'message' => "Reservasi {$loan->reservation_code} berhasil dibatalkan.",
            'data'    => $this->reservationPayload($loan),
        ]);
    }

    /**
     * GET /api/v1/member/loans
     */
    public function loans(Request $request): JsonResponse
    {
        $member = $this->member($request);

        $loans = Loan::with('collection.catalog')
            ->where('member_id', $member->id)
            ->whereIn('status', [...self::LOAN_ACTIVE, Loan::STATUS_RETURNED, Loan::STATUS_LOST])
            ->orderByRaw("FIELD(status, 'OVERDUE', 'BORROWED', 'LOST', 'RETURNED')")
            ->orderBy('due_date')
            ->limit(100)
            ->get()
            ->map(fn ($loan) => $this->loanPayload($loan));

        $active = $loans->whereIn('status', self::LOAN_ACTIVE);

        return response()->json([
            'success' => true,
            'message' => 'Daftar pinjaman berhasil diambil.',
            'data'    => [
                'eligibility' => $this->eligibility($member),
                'summary'     => [
                    'active'        => $active->count(),
                    'overdue'       => $active->where('state', 'overdue')->count(),
                    'due_soon'      => $active->filter(fn ($l) => $l['days_left'] !== null && $l['days_left'] >= 0 && $l['days_left'] <= 2)->count(),
                    'fine_estimate' => (float) $active->sum('fine'),
                    'returned'      => $loans->where('status', Loan::STATUS_RETURNED)->count(),
                ],
                'loans'       => $loans->values(),
            ],
        ]);
    }

    /**
     * GET /api/v1/member/favorites
     * Daftar ID katalog favorit anggota (untuk menandai bintang di OPAC & Detail Buku).
     */
    public function favorites(Request $request): JsonResponse
    {
        $member = $this->member($request);

        return response()->json([
            'success' => true,
            'message' => 'Daftar buku favorit berhasil diambil.',
            'data'    => [
                'catalog_ids' => $member->favoriteCatalogs()->pluck('catalogs.id')->values(),
            ],
        ]);
    }

    /**
     * POST /api/v1/member/favorites/{catalogId}/toggle
     */
    public function toggleFavorite(Request $request, int $catalogId): JsonResponse
    {
        $member = $this->member($request);

        if (! Catalog::where('id', $catalogId)->where('is_quarantine', false)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Buku tidak ditemukan di katalog.',
            ], 404);
        }

        $result = $member->favoriteCatalogs()->toggle($catalogId);
        $isFavorite = ! empty($result['attached']);

        return response()->json([
            'success' => true,
            'message' => $isFavorite ? 'Buku ditambahkan ke Favorit Saya.' : 'Buku dihapus dari Favorit Saya.',
            'data'    => [
                'catalog_id'  => $catalogId,
                'is_favorite' => $isFavorite,
            ],
        ]);
    }

    private function member(Request $request): Member
    {
        $user = $request->user();

        abort_unless($user instanceof Member, 403, 'Fitur ini hanya untuk akun anggota perpustakaan.');

        return $user;
    }

    private function eligibility(Member $member): array
    {
        $max = (int) get_setting('max_borrow_qty', 3);
        $used = Loan::where('member_id', $member->id)->whereIn('status', self::QUOTA_STATUSES)->count();

        $blockReason = $member->circulationBlockReason()
            ?? ($used >= $max ? "Kuota Anda sudah penuh ({$used}/{$max} buku, termasuk reservasi aktif)." : null);

        return [
            'quota'        => ['max' => $max, 'used' => $used, 'remaining' => max(0, $max - $used)],
            'eligible'     => $blockReason === null,
            'block_reason' => $blockReason,
        ];
    }

    private function bookPayload(Loan $loan): array
    {
        $collection = $loan->collection;
        $catalog = $collection?->catalog;

        return [
            'catalog_id'   => $catalog?->id,
            'title'        => $catalog?->title,
            'author'       => $catalog?->author_main ?: $catalog?->author_corporate,
            'publisher'    => $catalog?->publisher,
            'publish_year' => $catalog?->publish_year,
            'barcode_no'   => $collection?->barcode_no,
            'call_number'  => $collection?->exemplar_call_number ?: $catalog?->catalog_call_number,
            'location'     => $collection?->room_location_code,
        ];
    }

    private function reservationPayload(Loan $loan): array
    {
        $state = match ($loan->status) {
            Loan::STATUS_PENDING_CHECK    => 'pending',
            Loan::STATUS_READY_FOR_PICKUP => 'ready',
            Loan::STATUS_BORROWED, Loan::STATUS_OVERDUE, Loan::STATUS_RETURNED, Loan::STATUS_LOST => 'picked_up',
            Loan::STATUS_UNAVAILABLE      => 'unavailable',
            Loan::STATUS_EXPIRED          => 'expired',
            default                       => 'cancelled',
        };

        return [
            'id'               => $loan->id,
            'reservation_code' => $loan->reservation_code,
            'status'           => $loan->status,
            'state'            => $state,
            'book'             => $this->bookPayload($loan),
            'reserved_at'      => $loan->created_at?->toIso8601String(),
            'pickup_deadline'  => $loan->pickup_deadline?->toIso8601String(),
            'borrow_date'      => $loan->borrow_date?->toIso8601String(),
            'can_cancel'       => in_array($loan->status, self::RESERVATION_ACTIVE, true),
        ];
    }

    private function loanPayload(Loan $loan): array
    {
        $isActive = in_array($loan->status, self::LOAN_ACTIVE, true);
        $daysLeft = $isActive && $loan->due_date
            ? (int) now()->startOfDay()->diffInDays($loan->due_date->copy()->startOfDay(), false)
            : null;

        $state = match (true) {
            $loan->status === Loan::STATUS_RETURNED => 'returned',
            $loan->status === Loan::STATUS_LOST     => 'lost',
            $daysLeft !== null && $daysLeft < 0     => 'overdue',
            $daysLeft === 0                         => 'due_today',
            default                                 => 'on_time',
        };

        $fine = $isActive
            ? max(0, -($daysLeft ?? 0)) * (float) get_setting('fine_per_day', 1000)
            : (float) $loan->fine_amount;

        return [
            'id'               => $loan->id,
            'loan_number'      => $loan->loan_number,
            'reservation_code' => $loan->reservation_code,
            'status'           => $loan->status,
            'state'            => $state,
            'book'             => $this->bookPayload($loan),
            'loan_date'        => $loan->loan_date?->toDateString(),
            'due_date'         => $loan->due_date?->toDateString(),
            'return_date'      => $loan->return_date?->toDateString(),
            'days_left'        => $daysLeft,
            'late_days'        => $isActive ? max(0, -($daysLeft ?? 0)) : (int) $loan->late_days,
            'fine'             => $fine,
            'extension_count'  => (int) $loan->extension_count,
        ];
    }
}
