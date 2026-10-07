<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LockerLoanResource;
use App\Services\LockerService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Loker",
    description: "API Modul Peminjaman & Pengembalian Kunci Loker Perpustakaan"
)]
class LockerController extends Controller
{
    protected LockerService $lockerService;

    public function __construct(LockerService $lockerService)
    {
        $this->lockerService = $lockerService;
    }

    #[OA\Post(
        path: "/admin/lockers/borrow",
        summary: "Peminjaman loker (Validation 1 active loan per member)",
        description: "Pencatatan transaksi peminjaman kunci loker. Memastikan member belum memiliki transaksi loker aktif.",
        operationId: "borrowLockerKey",
        tags: ["Loker"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["member_no", "locker_code"],
                properties: [
                    new OA\Property(property: "member_no", type: "string", example: "MEM-202610-0001", description: "Hasil scan barcode kartu member"),
                    new OA\Property(property: "locker_code", type: "string", example: "LCK-01", description: "Hasil scan barcode kunci loker"),
                    new OA\Property(property: "deposit_type", type: "string", example: "KTP", description: "Jenis jaminan (KTP/KTM/SIM/dll)")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Peminjaman loker berhasil dicatat",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Peminjaman kunci loker LCK-01 berhasil dicatat!"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validasi gagal / Member memiliki pinjaman loker aktif",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Anggota sedang memiliki peminjaman loker aktif.")
                    ]
                )
            )
        ]
    )]
    public function borrow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_no'    => 'required|string',
            'locker_code'  => 'required|string',
            'deposit_type' => 'nullable|string|max:100',
        ], [
            'member_no.required'   => 'Nomor anggota (barcode member) wajib diisi/dipindai.',
            'locker_code.required' => 'Kode loker (barcode kunci) wajib diisi/dipindai.',
        ]);

        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $loan = $this->lockerService->borrowLocker(
                $validated['member_no'],
                $validated['locker_code'],
                $validated['deposit_type'] ?? 'KTP',
                $operatorAdmin?->id
            );

            $loan->load(['member', 'locker', 'processedByAdmin']);

            return response()->json([
                'success' => true,
                'message' => "Peminjaman kunci loker {$loan->locker->locker_code} berhasil dicatat! No. Transaksi: {$loan->loan_number}.",
                'data'    => new LockerLoanResource($loan),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Post(
        path: "/admin/lockers/return",
        summary: "Pengembalian loker",
        description: "Petugas memproses pengembalian kunci loker berdasarkan barcode loker atau nomor member.",
        operationId: "returnLockerKey",
        tags: ["Loker"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "locker_code", type: "string", example: "LCK-01"),
                    new OA\Property(property: "member_no", type: "string", example: "MEM-202610-0001"),
                    new OA\Property(property: "violation_note", type: "string", example: "Kunci agak bengkok / Tidak ada pelanggaran")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Pengembalian loker berhasil diproses",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Pengembalian kunci loker LCK-01 berhasil diproses."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function returnLocker(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locker_code'     => 'required_without:member_no|nullable|string',
            'member_no'       => 'required_without:locker_code|nullable|string',
            'violation_note'  => 'nullable|string',
        ], [
            'locker_code.required_without' => 'Kode loker atau nomor member wajib diisi/dipindai.',
            'member_no.required_without'   => 'Kode loker atau nomor member wajib diisi/dipindai.',
        ]);

        $identifier = $request->input('locker_code') ?: $request->input('member_no');
        $operatorAdmin = Auth::guard('admin')->user();

        try {
            $loan = $this->lockerService->returnLocker(
                $identifier,
                $request->input('violation_note'),
                $operatorAdmin?->id
            );

            $loan->load(['member', 'locker', 'processedByAdmin']);

            return response()->json([
                'success' => true,
                'message' => "Pengembalian kunci loker {$loan->locker->locker_code} berhasil diproses.",
                'data'    => new LockerLoanResource($loan),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Get(
        path: "/admin/lockers/status",
        summary: "Monitoring status loker",
        description: "Menampilkan seluruh status dan riwayat transaksi peminjaman loker.",
        operationId: "getLockerStatusAndHistory",
        tags: ["Loker"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "status",
                in: "query",
                description: "Filter status (BORROWED, RETURNED)",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Pencarian kode loker, no transaksi, atau nama member",
                required: false,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Daftar riwayat peminjaman loker berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Daftar riwayat peminjaman loker berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function loansHistory(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $loans = $this->lockerService->getLoansHistory($status, $search);

        return response()->json([
            'success' => true,
            'message' => 'Daftar riwayat peminjaman loker berhasil diambil.',
            'data'    => LockerLoanResource::collection($loans)->response()->getData(true),
        ], 200);
    }
}
