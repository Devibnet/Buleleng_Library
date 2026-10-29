<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\CirculationService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modul Reservasi (Loket Sirkulasi) untuk Dashboard Admin SPA.
 * Alur: verifikasi buku di rak (PENDING_CHECK -> READY_FOR_PICKUP / UNAVAILABLE)
 *       -> proses pengambilan di loket (READY_FOR_PICKUP -> BORROWED).
 */
class ReservationDeskController extends Controller
{
    private const HISTORY_STATUSES = [
        Loan::STATUS_BORROWED,
        Loan::STATUS_OVERDUE,
        Loan::STATUS_RETURNED,
        Loan::STATUS_LOST,
        Loan::STATUS_UNAVAILABLE,
        Loan::STATUS_EXPIRED,
        Loan::STATUS_CANCELLED,
    ];

    public function __construct(protected CirculationService $circulationService)
    {
    }

    /**
     * GET /api/v1/admin/circulation/reservation-desk
     * Query: tab (pending|ready|history), search, per_page, page
     */
    public function index(Request $request): JsonResponse
    {
        $this->circulationService->handleExpiredReservations();

        $perPage = min(max((int) $request->query('per_page', 10), 5), 100);
        $tab = $request->query('tab', 'pending');

        $query = $this->baseQuery()->with(['member', 'collection.catalog', 'createdByAdmin']);

        match ($tab) {
            'ready'   => $query->where('status', Loan::STATUS_READY_FOR_PICKUP)->orderBy('pickup_deadline'),
            'history' => $query->whereIn('status', self::HISTORY_STATUSES)->orderByDesc('updated_at'),
            default   => $query->where('status', Loan::STATUS_PENDING_CHECK)->orderBy('created_at'),
        };

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('reservation_code', 'like', "%{$search}%")
                    ->orWhereHas('member', fn ($mq) => $mq->where('name', 'like', "%{$search}%")->orWhere('member_no', 'like', "%{$search}%"))
                    ->orWhereHas('collection', function ($cq) use ($search) {
                        $cq->where('barcode_no', 'like', "%{$search}%")
                            ->orWhereHas('catalog', fn ($kq) => $kq->where('title', 'like', "%{$search}%"));
                    });
            });
        }

        $reservations = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar reservasi berhasil diambil.',
            'stats'   => $this->stats(),
            'data'    => collect($reservations->items())->map(fn ($loan) => $this->payload($loan))->values(),
            'meta'    => [
                'current_page' => $reservations->currentPage(),
                'last_page'    => $reservations->lastPage(),
                'per_page'     => $reservations->perPage(),
                'total'        => $reservations->total(),
                'from'         => $reservations->firstItem(),
                'to'           => $reservations->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/circulation/reservation-desk/lookup?code=RSV-...
     * Pencarian cepat kode reservasi yang ditunjukkan anggota di loket.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);
        $this->circulationService->handleExpiredReservations();

        $loan = $this->baseQuery()
            ->with(['member', 'collection.catalog', 'createdByAdmin'])
            ->where('reservation_code', trim($request->query('code')))
            ->first();

        if (! $loan) {
            return response()->json([
                'success' => false,
                'message' => "Kode reservasi '{$request->query('code')}' tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reservasi ditemukan.',
            'data'    => $this->payload($loan),
        ]);
    }

    /**
     * POST /api/v1/admin/circulation/reservation-desk/{id}/confirm-ready
     */
    public function confirmReady(Request $request, int $id): JsonResponse
    {
        return $this->runAction(fn () => $this->circulationService->confirmReady($id, $request->user()?->id),
            fn (Loan $loan) => "Reservasi {$loan->reservation_code} siap diambil. Batas pengambilan: " . $loan->pickup_deadline?->format('d/m/Y') . '.');
    }

    /**
     * POST /api/v1/admin/circulation/reservation-desk/{id}/mark-unavailable
     */
    public function markUnavailable(Request $request, int $id): JsonResponse
    {
        return $this->runAction(fn () => $this->circulationService->markUnavailable($id, null, $request->user()?->id),
            fn (Loan $loan) => "Reservasi {$loan->reservation_code} ditandai Tidak Tersedia. Kuota anggota dikembalikan.");
    }

    /**
     * POST /api/v1/admin/circulation/reservation-desk/{id}/pickup
     * Body: { duration_days }
     */
    public function pickup(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'duration_days' => 'required|integer|min:1|max:30',
        ], [
            'duration_days.max' => 'Durasi peminjaman maksimal 30 hari.',
        ]);

        $loan = $this->baseQuery()->with('member')->find($id);

        if (! $loan) {
            return response()->json(['success' => false, 'message' => 'Reservasi tidak ditemukan.'], 404);
        }

        if ($loan->status !== Loan::STATUS_READY_FOR_PICKUP) {
            return response()->json([
                'success' => false,
                'message' => "Reservasi {$loan->reservation_code} belum siap diambil (Status: {$loan->status}). Verifikasi buku di rak terlebih dahulu.",
            ], 422);
        }

        if ($blockReason = $loan->member?->circulationBlockReason()) {
            return response()->json([
                'success' => false,
                'message' => "Pengambilan ditolak: {$blockReason}",
            ], 422);
        }

        return $this->runAction(
            fn () => $this->circulationService->confirmPickup($loan->reservation_code, $request->user()?->id, (int) $validated['duration_days']),
            fn (Loan $loan) => "Buku diserahkan kepada {$loan->member?->name}. No. Peminjaman {$loan->loan_number}, jatuh tempo " . $loan->due_date?->format('d/m/Y') . '.'
        );
    }

    private function runAction(callable $action, callable $message): JsonResponse
    {
        try {
            $loan = $action();
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $loan->load(['member', 'collection.catalog', 'createdByAdmin']);

        return response()->json([
            'success' => true,
            'message' => $message($loan),
            'data'    => $this->payload($loan),
            'stats'   => $this->stats(),
        ]);
    }

    private function baseQuery(): Builder
    {
        return Loan::whereNotNull('reservation_code');
    }

    private function stats(): array
    {
        $today = now()->toDateString();

        return [
            'pending'          => $this->baseQuery()->where('status', Loan::STATUS_PENDING_CHECK)->count(),
            'ready'            => $this->baseQuery()->where('status', Loan::STATUS_READY_FOR_PICKUP)->count(),
            'ready_due_today'  => $this->baseQuery()->where('status', Loan::STATUS_READY_FOR_PICKUP)->whereDate('pickup_deadline', $today)->count(),
            'picked_up_today'  => $this->baseQuery()->whereNotNull('borrow_date')->whereDate('borrow_date', $today)->count(),
            'new_today'        => $this->baseQuery()->whereDate('created_at', $today)->count(),
            'failed_month'     => $this->baseQuery()
                ->whereIn('status', [Loan::STATUS_EXPIRED, Loan::STATUS_UNAVAILABLE, Loan::STATUS_CANCELLED])
                ->where('updated_at', '>=', now()->startOfMonth())
                ->count(),
            'history'          => $this->baseQuery()->whereIn('status', self::HISTORY_STATUSES)->count(),
        ];
    }

    private function payload(Loan $loan): array
    {
        $collection = $loan->collection;
        $catalog = $collection?->catalog;
        $member = $loan->member;

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
            'loan_number'      => $loan->loan_number,
            'status'           => $loan->status,
            'state'            => $state,
            'book'             => [
                'title'        => $catalog?->title,
                'author'       => $catalog?->author_main ?: $catalog?->author_corporate,
                'publisher'    => $catalog?->publisher,
                'publish_year' => $catalog?->publish_year,
                'ddc_number'   => $catalog?->ddc_number,
                'barcode_no'   => $collection?->barcode_no,
                'call_number'  => $collection?->exemplar_call_number ?: $catalog?->catalog_call_number,
                'location'     => $collection?->room_location_code,
            ],
            'member'           => [
                'name'             => $member?->name,
                'member_no'        => $member?->member_no,
                'job_category'     => $member?->job_category,
                'institution_name' => $member?->institution_name,
                'phone_number'     => $member?->phone_number,
                'block_reason'     => $member?->circulationBlockReason(),
            ],
            'reserved_at'      => $loan->created_at?->toIso8601String(),
            'pickup_deadline'  => $loan->pickup_deadline?->toIso8601String(),
            'borrow_date'      => $loan->borrow_date?->toIso8601String(),
            'due_date'         => $loan->due_date?->toDateString(),
            'updated_at'       => $loan->updated_at?->toIso8601String(),
            'handled_by'       => $loan->createdByAdmin?->name,
        ];
    }
}
