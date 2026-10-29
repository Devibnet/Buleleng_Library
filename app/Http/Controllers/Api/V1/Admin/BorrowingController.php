<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
use App\Services\CirculationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Modul Peminjaman Buku (Loket Sirkulasi) untuk Dashboard Admin SPA.
 * Alur: identifikasi anggota -> scan eksemplar -> konfirmasi peminjaman (batch).
 */
class BorrowingController extends Controller
{
    // Reservasi aktif ikut menghabiskan kuota pinjam anggota
    private const ACTIVE_STATUSES = [
        Loan::STATUS_PENDING_CHECK,
        Loan::STATUS_READY_FOR_PICKUP,
        Loan::STATUS_RESERVED,
        Loan::STATUS_BORROWED,
        Loan::STATUS_OVERDUE,
    ];

    private const BLOCKED_CONDITIONS = ['rusak', 'rusak ringan', 'rusak berat', 'hilang'];

    public function __construct(protected CirculationService $circulationService)
    {
    }

    /**
     * GET /api/v1/admin/circulation/borrow/member?code=...
     * Identifikasi anggota berdasarkan nomor anggota / NIK / email beserta sisa kuota pinjam.
     */
    public function member(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $member = $this->findMember(trim($request->query('code')));

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => "Anggota dengan nomor '{$request->query('code')}' tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data anggota berhasil diambil.',
            'data'    => $this->memberPayload($member),
        ]);
    }

    /**
     * GET /api/v1/admin/circulation/borrow/collection?barcode=...
     * Validasi eksemplar buku berdasarkan barcode sebelum ditambahkan ke daftar pinjam.
     */
    public function collection(Request $request): JsonResponse
    {
        $request->validate(['barcode' => 'required|string']);

        $collection = Collection::with('catalog')
            ->where('barcode_no', trim($request->query('barcode')))
            ->first();

        if (! $collection) {
            return response()->json([
                'success' => false,
                'message' => "Buku dengan barcode '{$request->query('barcode')}' tidak ditemukan.",
            ], 404);
        }

        $reason = $this->collectionBlockReason($collection);

        if ($reason) {
            return response()->json([
                'success' => false,
                'message' => $reason,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Eksemplar buku tersedia untuk dipinjam.',
            'data'    => $this->collectionPayload($collection),
        ]);
    }

    /**
     * GET /api/v1/admin/circulation/borrow/collections/search?q=...
     * Pencarian eksemplar tersedia berdasarkan judul / pengarang / sebagian barcode,
     * untuk buku fisik yang label barcodenya belum ditempel.
     */
    public function searchCollections(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);
        $keyword = trim($request->query('q'));

        $collections = Collection::with('catalog')
            ->where('availability_status', 'AVAILABLE')
            ->where('is_quarantine', false)
            ->where(function ($q) {
                $q->whereNull('physical_condition')
                    ->orWhereNotIn('physical_condition', ['Rusak', 'Rusak Ringan', 'Rusak Berat', 'Hilang']);
            })
            ->where(function ($q) use ($keyword) {
                $q->where('barcode_no', 'like', "%{$keyword}%")
                    ->orWhereHas('catalog', function ($cq) use ($keyword) {
                        $cq->where('title', 'like', "%{$keyword}%")
                            ->orWhere('author_main', 'like', "%{$keyword}%");
                    });
            })
            ->orderBy('catalog_id')
            ->orderBy('barcode_no')
            ->limit(15)
            ->get();

        return response()->json([
            'success' => true,
            'message' => $collections->isEmpty()
                ? "Tidak ada eksemplar tersedia yang cocok dengan '{$keyword}'."
                : 'Eksemplar tersedia ditemukan.',
            'data'    => $collections->map(fn ($collection) => $this->collectionPayload($collection))->values(),
        ]);
    }

    /**
     * POST /api/v1/admin/circulation/borrow
     * Body: { member_no, barcodes: [..], duration_days }
     * Seluruh eksemplar dicatat dalam satu transaksi database (gagal satu = batal semua).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_no'     => 'required|string',
            'barcodes'      => 'required|array|min:1',
            'barcodes.*'    => 'required|string|distinct',
            'duration_days' => 'required|integer|min:1|max:30',
        ], [
            'barcodes.required'      => 'Minimal satu buku harus dipindai.',
            'barcodes.*.distinct'    => 'Terdapat barcode buku yang ganda.',
            'duration_days.max'      => 'Durasi peminjaman maksimal 30 hari.',
        ]);

        $member = $this->findMember($validated['member_no']);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => "Anggota dengan nomor '{$validated['member_no']}' tidak ditemukan.",
            ], 404);
        }

        $memberData = $this->memberPayload($member);

        if (! $memberData['eligible']) {
            return response()->json([
                'success' => false,
                'message' => $memberData['block_reason'],
            ], 422);
        }

        if (count($validated['barcodes']) > $memberData['quota']['remaining']) {
            return response()->json([
                'success' => false,
                'message' => "Sisa kuota pinjam {$member->name} hanya {$memberData['quota']['remaining']} buku.",
            ], 422);
        }

        $operatorId = $request->user()?->id;

        try {
            $loans = DB::transaction(function () use ($validated, $member, $operatorId) {
                return collect($validated['barcodes'])->map(fn ($barcode) => $this->circulationService->processDirectBorrow(
                    $member->member_no,
                    $barcode,
                    $operatorId,
                    (int) $validated['duration_days']
                ));
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $loans->each->load(['member', 'collection.catalog', 'createdByAdmin']);

        return response()->json([
            'success' => true,
            'message' => "Peminjaman {$loans->count()} buku atas nama {$member->name} berhasil dicatat.",
            'data'    => [
                'member' => $this->memberPayload($member->fresh()),
                'loans'  => LoanResource::collection($loans),
            ],
        ], 201);
    }

    private function findMember(string $code): ?Member
    {
        return Member::where('member_no', $code)
            ->orWhere('nik', $code)
            ->orWhere('email', $code)
            ->first();
    }

    private function memberPayload(Member $member): array
    {
        $max = (int) get_setting('max_borrow_qty', 3);
        $active = Loan::where('member_id', $member->id)->whereIn('status', self::ACTIVE_STATUSES)->count();
        $status = $member->status ?: 'Aktif Bebas Pustaka';

        $blockReason = $member->circulationBlockReason()
            ?? ($active >= $max ? "Kuota pinjam sudah penuh ({$active}/{$max} buku, termasuk reservasi aktif)." : null);

        return [
            'id'               => $member->id,
            'member_no'        => $member->member_no,
            'name'             => $member->name,
            'job_category'     => $member->job_category,
            'institution_name' => $member->institution_name,
            'status'           => $status,
            'valid_until'      => $member->valid_until?->format('Y-m-d'),
            'photo_url'        => $member->photo_url,
            'quota'            => [
                'max'       => $max,
                'active'    => $active,
                'remaining' => max(0, $max - $active),
            ],
            'eligible'         => $blockReason === null,
            'block_reason'     => $blockReason,
        ];
    }

    private function collectionBlockReason(Collection $collection): ?string
    {
        if ($collection->is_quarantine) {
            return 'Buku ini sedang dalam masa karantina.';
        }

        if ($collection->availability_status !== 'AVAILABLE') {
            return "Buku ini sedang tidak tersedia (Status: {$collection->availability_status}).";
        }

        if (in_array(strtolower($collection->physical_condition ?? 'baik'), self::BLOCKED_CONDITIONS)) {
            return "Eksemplar tidak dapat dipinjam karena kondisi fisik: {$collection->physical_condition}.";
        }

        return null;
    }

    private function collectionPayload(Collection $collection): array
    {
        $catalog = $collection->catalog;

        return [
            'id'          => $collection->id,
            'barcode_no'  => $collection->barcode_no,
            'title'       => $catalog?->title,
            'author'      => $catalog?->author_main ?: $catalog?->author_corporate,
            'call_number' => $collection->exemplar_call_number ?: $catalog?->catalog_call_number,
            'location'    => $collection->room_location_code,
            'condition'   => $collection->physical_condition,
        ];
    }
}
