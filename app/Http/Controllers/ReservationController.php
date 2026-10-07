<?php

namespace App\Http\Controllers;

use App\Http\Resources\LoanResource;
use App\Services\CirculationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class ReservationController extends Controller
{
    protected CirculationService $circulationService;

    public function __construct(CirculationService $circulationService)
    {
        $this->circulationService = $circulationService;
    }

    #[OA\Post(
        path: "/api/circulation/reserve",
        summary: "Reservasi online (Return status PENDING_CHECK)",
        description: "Member yang terautentikasi melakukan reservasi online. Status awal transaksi diset ke PENDING_CHECK dan stok berkurang (-1).",
        operationId: "reserveBookOnlineApi",
        tags: ["Sirkulasi"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID Katalog Buku",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: "Reservasi berhasil dibuat dengan status PENDING_CHECK",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Reservasi berhasil dibuat! Kode Reservasi Anda: RSV-20261005-0001."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Unauthenticated. Silakan login sebagai member terlebih dahulu.")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validasi gagal / stok kosong / kuota penuh",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Batas maksimal peminjaman dan reservasi aktif adalah 3 buku.")
                    ]
                )
            )
        ]
    )]
    public function reserve(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $member = Auth::guard('member')->user();

        if (! $member) {
            session(['intended_book_id' => $id]);
            session(['url.intended' => url("/books/{$id}")]);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success'     => false,
                    'message'     => 'Unauthenticated. Silakan login sebagai member terlebih dahulu untuk melakukan reservasi.',
                    'redirect_to' => route('auth.google'),
                ], 401);
            }

            return redirect()->route('auth.google')
                ->with('error', 'Silakan login terlebih dahulu untuk melanjutkan reservasi buku.');
        }

        try {
            $loan = $this->circulationService->reserveBook($member->id, $id);
            $loan->load(['collection.catalog', 'member']);

            session()->forget('intended_book_id');

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => "Reservasi berhasil dibuat! Kode Reservasi Anda: {$loan->reservation_code}. Silakan tunggu verifikasi fisik buku oleh petugas.",
                    'data'    => new LoanResource($loan),
                ], 201);
            }

            return redirect()->back()->with('success', "Reservasi berhasil dibuat! Kode: {$loan->reservation_code}");
        } catch (Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
