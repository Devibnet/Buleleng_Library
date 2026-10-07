<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestbookGroupRequest;
use App\Http\Requests\GuestbookMemberRequest;
use App\Http\Requests\GuestbookNonMemberRequest;
use App\Http\Resources\GuestbookResource;
use App\Services\GuestbookService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Buku Tamu",
    description: "API Pencatatan Kunjungan Buku Tamu (Member, Non-Member & Rombongan)"
)]
class GuestbookController extends Controller
{
    protected GuestbookService $guestbookService;

    public function __construct(GuestbookService $guestbookService)
    {
        $this->guestbookService = $guestbookService;
    }

    #[OA\Post(
        path: "/api/guestbooks/member",
        summary: "Scan barcode member untuk Kunjungan Anggota",
        description: "Mencatat kehadiran pengunjung anggota perpustakaan cukup dengan memindai barcode nomor anggota.",
        operationId: "storeMemberGuestbook",
        tags: ["Buku Tamu"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["member_no"],
                properties: [
                    new OA\Property(property: "member_no", type: "string", example: "MEM-202610-0001", description: "Barcode/No Anggota")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Kunjungan anggota berhasil dicatat",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Kunjungan anggota 'Angga Prabaswara' berhasil dicatat."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Member tidak ditemukan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Member dengan nomor 'MEM-999' tidak ditemukan.")
                    ]
                )
            )
        ]
    )]
    public function storeMember(GuestbookMemberRequest $request): JsonResponse
    {
        try {
            $guestbook = $this->guestbookService->recordMemberVisit(
                $request->validated('member_no')
            );

            $guestbook->load('member');

            return response()->json([
                'success' => true,
                'message' => "Kunjungan anggota '{$guestbook->visitor_name}' berhasil dicatat.",
                'data'    => new GuestbookResource($guestbook),
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Post(
        path: "/api/guestbooks/non-member",
        summary: "Form kunjungan pengunjung umum (Non-Anggota)",
        description: "Mencatat data identitas pengunjung umum non-anggota.",
        operationId: "storeNonMemberGuestbook",
        tags: ["Buku Tamu"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["visitor_name"],
                properties: [
                    new OA\Property(property: "visitor_name", type: "string", example: "Budi Santoso"),
                    new OA\Property(property: "phone_number", type: "string", example: "081234567890"),
                    new OA\Property(property: "institution_name", type: "string", example: "Masyarakat Umum"),
                    new OA\Property(property: "address", type: "string", example: "Singaraja, Buleleng")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Kunjungan non-anggota berhasil dicatat",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Kunjungan non-anggota 'Budi Santoso' berhasil dicatat."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function storeNonMember(GuestbookNonMemberRequest $request): JsonResponse
    {
        try {
            $guestbook = $this->guestbookService->recordNonMemberVisit(
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => "Kunjungan non-anggota '{$guestbook->visitor_name}' berhasil dicatat.",
                'data'    => new GuestbookResource($guestbook),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Post(
        path: "/api/guestbooks/group",
        summary: "Form kunjungan rombongan (termasuk breakdown JSON job & education)",
        description: "Mencatat kunjungan rombongan dari sekolah/instansi beserta rincian demografi jumlah laki-laki, perempuan, pekerjaan, dan pendidikan.",
        operationId: "storeGroupGuestbook",
        tags: ["Buku Tamu"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["institution_name", "visitor_name", "total_personnel"],
                properties: [
                    new OA\Property(property: "institution_name", type: "string", example: "SMA Negeri 1 Singaraja"),
                    new OA\Property(property: "visitor_name", type: "string", example: "Drs. Putu Wijaya (PJ Rombongan)"),
                    new OA\Property(property: "total_personnel", type: "integer", example: 40),
                    new OA\Property(property: "gender_male_count", type: "integer", example: 20),
                    new OA\Property(property: "gender_female_count", type: "integer", example: 20),
                    new OA\Property(property: "job_breakdown", type: "object", example: ["PELAJAR" => 38, "GURU" => 2]),
                    new OA\Property(property: "education_breakdown", type: "object", example: ["SMA" => 38, "S1" => 2])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Kunjungan rombongan berhasil dicatat",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Kunjungan rombongan 'SMA Negeri 1 Singaraja' berhasil dicatat."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function storeGroup(GuestbookGroupRequest $request): JsonResponse
    {
        try {
            $guestbook = $this->guestbookService->recordGroupVisit(
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => "Kunjungan rombongan '{$guestbook->institution_name}' (PJ: {$guestbook->visitor_name}) sebanyak {$guestbook->total_personnel} orang berhasil dicatat.",
                'data'    => new GuestbookResource($guestbook),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Get(
        path: "/admin/guestbooks/reports",
        summary: "Rekap & Laporan Kunjungan Buku Tamu",
        description: "Menampilkan rekapitulasi data kunjungan buku tamu harian dan log detail.",
        operationId: "getGuestbookReports",
        tags: ["Buku Tamu"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "visit_type", in: "query", required: false, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Laporan buku tamu berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Laporan dan rekapitulasi buku tamu berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function reports(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $visitType = $request->query('visit_type');

        $reports = $this->guestbookService->getReports($startDate, $endDate, $visitType);

        return response()->json([
            'success' => true,
            'message' => 'Laporan dan rekapitulasi buku tamu berhasil diambil.',
            'data'    => [
                'summary'       => $reports['summary'],
                'daily_summary' => $reports['daily_summary'],
                'logs'          => GuestbookResource::collection($reports['logs'])->response()->getData(true),
            ],
        ], 200);
    }
}
