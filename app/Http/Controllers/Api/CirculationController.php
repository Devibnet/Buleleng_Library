<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\CirculationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Sirkulasi",
    description: "API Transaksi Sirkulasi (Peminjaman, Pengembalian, Reservasi, Perpanjangan & Denda)"
)]
class CirculationController extends Controller
{
    protected CirculationService $circulationService;

    public function __construct(CirculationService $circulationService)
    {
        $this->circulationService = $circulationService;
    }

    #[OA\Get(
        path: "/api/v1/admin/circulation/loans",
        summary: "List transaksi sirkulasi",
        description: "Menampilkan seluruh daftar transaksi sirkulasi dengan pencarian dan filter status.",
        operationId: "getCirculationLoans",
        tags: ["Sirkulasi"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "status",
                in: "query",
                description: "Filter status transaksi (ALL, PENDING_CHECK, READY_FOR_PICKUP, BORROWED, RETURNED, OVERDUE, UNAVAILABLE)",
                required: false,
                schema: new OA\Schema(type: "string", default: "ALL")
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Pencarian kode reservasi, no peminjaman, nama member, atau barcode buku",
                required: false,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Daftar transaksi sirkulasi berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Daftar transaksi sirkulasi berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'ALL');
        $search = $request->query('search');

        $query = Loan::with(['member', 'collection.catalog', 'createdByAdmin', 'returnedByAdmin']);

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

        return response()->json([
            'success' => true,
            'message' => 'Daftar transaksi sirkulasi berhasil diambil.',
            'data'    => LoanResource::collection($loans)->response()->getData(true),
        ], 200);
    }

    #[OA\Post(
        path: "/admin/circulation/borrow",
        summary: "Direct borrow offline (Peminjaman Langsung)",
        description: "Petugas loket memproses peminjaman langsung buku fisik kepada anggota.",
        operationId: "storeDirectBorrow",
        tags: ["Sirkulasi"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["member_no", "book_barcode"],
                properties: [
                    new OA\Property(property: "member_no", type: "string", example: "MEM-202610-0001", description: "Barcode kartu member"),
                    new OA\Property(property: "book_barcode", type: "string", example: "COL-1001", description: "Barcode eksemplar buku"),
                    new OA\Property(property: "duration_days", type: "integer", example: 7, description: "Durasi peminjaman (default 7 hari)")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Peminjaman langsung berhasil dicatat",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Peminjaman langsung berhasil dicatat! No. Peminjaman: LON-20261005-0001."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validasi gagal / Kuota peminjaman penuh",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Batas maksimal peminjaman dan reservasi aktif adalah 3 buku.")
                    ]
                )
            )
        ]
    )]
    public function storeDirect(Request $request): JsonResponse
    {
        $request->validate([
            'member_no'    => 'required|string',
            'book_barcode' => 'required|string',
            'duration_days' => 'nullable|integer|min:1|max:30',
        ]);

        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $loan = $this->circulationService->processDirectBorrow(
                $request->input('member_no'),
                $request->input('book_barcode'),
                $operatorAdmin?->id,
                (int) ($request->input('duration_days', 7))
            );

            $loan->load(['member', 'collection.catalog', 'createdByAdmin']);

            return response()->json([
                'success' => true,
                'message' => "Peminjaman langsung berhasil dicatat! No. Peminjaman: {$loan->loan_number}.",
                'data'    => new LoanResource($loan),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Post(
        path: "/api/v1/admin/circulation/confirm-reservation",
        summary: "Konfirmasi Pengambilan Buku Reservasi di Loket",
        description: "Petugas menyerahkan buku reservasi ke member dan mengubah status menjadi BORROWED.",
        operationId: "confirmReservationPickup",
        tags: ["Sirkulasi"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["reservation_code"],
                properties: [
                    new OA\Property(property: "reservation_code", type: "string", example: "RSV-20261005-0001"),
                    new OA\Property(property: "duration_days", type: "integer", example: 7)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Reservasi berhasil dikonfirmasi menjadi BORROWED",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Reservasi RSV-20261005-0001 berhasil dikonfirmasi! Status menjadi BORROWED."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function confirmReservation(Request $request): JsonResponse
    {
        $request->validate([
            'reservation_code' => 'required|string',
            'duration_days'    => 'nullable|integer|min:1|max:30',
        ]);

        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $loan = $this->circulationService->confirmPickup(
                $request->input('reservation_code'),
                $operatorAdmin?->id,
                (int) ($request->input('duration_days', 7))
            );

            $loan->load(['member', 'collection.catalog', 'createdByAdmin']);

            return response()->json([
                'success' => true,
                'message' => "Reservasi {$loan->reservation_code} berhasil dikonfirmasi! Status menjadi BORROWED.",
                'data'    => new LoanResource($loan),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Post(
        path: "/admin/circulation/return",
        summary: "Pengembalian & kalkulasi denda",
        description: "Petugas memproses pengembalian buku, otomatis menghitung hari keterlambatan dan nominal denda.",
        operationId: "returnLoanBook",
        tags: ["Sirkulasi"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["member_no", "book_barcode"],
                properties: [
                    new OA\Property(property: "member_no", type: "string", example: "MEM-202610-0001"),
                    new OA\Property(property: "book_barcode", type: "string", example: "COL-1001")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Buku berhasil dikembalikan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Buku dengan barcode 'COL-1001' berhasil dikembalikan."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function returnLoan(Request $request): JsonResponse
    {
        $request->validate([
            'member_no'    => 'required|string',
            'book_barcode' => 'required|string',
        ]);

        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $updatedLoan = $this->circulationService->processReturn(
                $request->input('member_no'),
                $request->input('book_barcode'),
                $operatorAdmin?->id
            );

            $updatedLoan->load(['member', 'collection.catalog', 'returnedByAdmin']);

            return response()->json([
                'success' => true,
                'message' => "Buku dengan barcode '{$request->input('book_barcode')}' milik anggota '{$request->input('member_no')}' berhasil dikembalikan.",
                'data'    => new LoanResource($updatedLoan),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Post(
        path: "/admin/circulation/renew",
        summary: "Perpanjangan buku",
        description: "Memperpanjang masa pinjam buku yang sedang dipinjam (+7 hari).",
        operationId: "renewLoanBook",
        tags: ["Sirkulasi"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["book_barcode"],
                properties: [
                    new OA\Property(property: "book_barcode", type: "string", example: "COL-1001"),
                    new OA\Property(property: "extension_days", type: "integer", example: 7)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Peminjaman berhasil diperpanjang",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Peminjaman buku barcode 'COL-1001' berhasil diperpanjang."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function renewLoan(Request $request): JsonResponse
    {
        $request->validate([
            'book_barcode'   => 'required|string',
            'extension_days' => 'nullable|integer|min:1|max:14',
        ]);

        try {
            $loan = $this->circulationService->processRenewal(
                $request->input('book_barcode'),
                (int) ($request->input('extension_days', 7))
            );

            $loan->load(['member', 'collection.catalog']);

            return response()->json([
                'success' => true,
                'message' => "Peminjaman buku barcode '{$request->input('book_barcode')}' berhasil diperpanjang hingga " . $loan->due_date->format('Y-m-d') . ". (Perpanjangan ke-{$loan->extension_count})",
                'data'    => new LoanResource($loan),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Get(
        path: "/admin/circulation/reservations/pending",
        summary: "List reservasi perlu verifikasi fisik",
        description: "Menampilkan daftar seluruh reservasi online baru dengan status PENDING_CHECK yang memerlukan verifikasi fisik di rak oleh petugas.",
        operationId: "getPendingReservations",
        tags: ["Sirkulasi"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Daftar reservasi pending berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Daftar reservasi pending untuk verifikasi rak berhasil diambil."),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function pendingReservations(Request $request): JsonResponse
    {
        $pendingLoans = $this->circulationService->getPendingReservations();

        return response()->json([
            'success' => true,
            'message' => 'Daftar reservasi pending untuk verifikasi rak berhasil diambil.',
            'data'    => LoanResource::collection($pendingLoans),
        ], 200);
    }

    #[OA\Post(
        path: "/admin/circulation/reservations/{id}/confirm-ready",
        summary: "Set READY_FOR_PICKUP & hitung H+3 kerja",
        description: "Petugas mengonfirmasi bahwa fisik buku ada di tangan. Status diubah ke READY_FOR_PICKUP dan tenggat H+3 kerja mulai dihitung.",
        operationId: "confirmReservationReady",
        tags: ["Sirkulasi"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID Transaksi Peminjaman / Kode Reservasi",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Fisik buku dikonfirmasi ready for pickup",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Reservasi #LON-20261005-0001 berhasil diverifikasi! Physical book ready for pickup."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function confirmReady(Request $request, $id): JsonResponse
    {
        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $loan = $this->circulationService->confirmReady($id, $operatorAdmin?->id);
            $loan->load(['member', 'collection.catalog']);

            return response()->json([
                'success' => true,
                'message' => "Reservasi #{$loan->loan_number} berhasil diverifikasi! Physical book ready for pickup. Deadline: " . $loan->pickup_deadline?->format('d/m/Y H:i'),
                'data'    => new LoanResource($loan),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Post(
        path: "/admin/circulation/reservations/{id}/mark-unavailable",
        summary: "Set UNAVAILABLE if lost/misplaced",
        description: "Petugas menandai buku tidak ditemukan di rak (salah taruh/hilang). Reservasi dibatalkan dan stok available_copies dikembalikan (+1).",
        operationId: "markReservationUnavailable",
        tags: ["Sirkulasi"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID Transaksi Peminjaman / Kode Reservasi",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "reason", type: "string", example: "Buku salah taruh di rak koleksi umum")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Reservasi ditandai UNAVAILABLE dan stok dikembalikan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Reservasi #LON-20261005-0001 ditandai UNAVAILABLE. Stok telah dikembalikan."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function markUnavailable(Request $request, $id): JsonResponse
    {
        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $loan = $this->circulationService->markUnavailable(
                $id,
                $request->input('reason', 'Buku salah taruh / hilang di rak'),
                $operatorAdmin?->id
            );
            $loan->load(['member', 'collection.catalog']);

            return response()->json([
                'success' => true,
                'message' => "Reservasi #{$loan->loan_number} ditandai UNAVAILABLE (buku tidak ditemukan). Stok telah dikembalikan.",
                'data'    => new LoanResource($loan),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
