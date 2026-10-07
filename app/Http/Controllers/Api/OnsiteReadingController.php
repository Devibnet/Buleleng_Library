<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnsiteReadingGuestRequest;
use App\Http\Requests\OnsiteReadingMemberRequest;
use App\Http\Resources\OnsiteReadingResource;
use App\Services\OnsiteReadingService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Baca di Tempat",
    description: "API Pencatatan Aktivitas Baca di Tempat (Onsite Reading)"
)]
class OnsiteReadingController extends Controller
{
    protected OnsiteReadingService $onsiteReadingService;

    public function __construct(OnsiteReadingService $onsiteReadingService)
    {
        $this->onsiteReadingService = $onsiteReadingService;
    }

    #[OA\Post(
        path: "/api/onsite-readings/member",
        summary: "Scan barcode member & buku untuk Baca di Tempat",
        description: "Mencatat aktivitas baca di tempat untuk anggota berdasarkan scan barcode member dan barcode eksemplar buku.",
        operationId: "storeMemberOnsiteReading",
        tags: ["Baca di Tempat"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["member_no", "collection_code"],
                properties: [
                    new OA\Property(property: "member_no", type: "string", example: "MEM-202610-0001"),
                    new OA\Property(property: "collection_code", type: "string", example: "COL-1001")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Aktivitas baca di tempat anggota berhasil dicatat",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Aktivitas baca di tempat untuk anggota 'Angga Prabaswara' berhasil dicatat."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function storeMember(OnsiteReadingMemberRequest $request): JsonResponse
    {
        try {
            $reading = $this->onsiteReadingService->recordMemberReading(
                $request->validated('member_no'),
                $request->validated('collection_code')
            );

            $reading->load(['member', 'guestbook', 'collection.catalog']);

            return response()->json([
                'success' => true,
                'message' => "Aktivitas baca di tempat untuk anggota '{$reading->member->name}' pada buku '{$reading->collection->catalog->title}' berhasil dicatat.",
                'data'    => new OnsiteReadingResource($reading),
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
        path: "/api/onsite-readings/guest",
        summary: "Entry baca di tempat pengunjung non-anggota (Guestbook)",
        description: "Mencatat aktivitas baca di tempat untuk pengunjung non-anggota yang telah mengisi buku tamu (guestbook_id).",
        operationId: "storeGuestOnsiteReading",
        tags: ["Baca di Tempat"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["guestbook_id", "collection_code"],
                properties: [
                    new OA\Property(property: "guestbook_id", type: "integer", example: 1),
                    new OA\Property(property: "collection_code", type: "string", example: "COL-1001")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Aktivitas baca di tempat non-anggota berhasil dicatat",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Aktivitas baca di tempat untuk pengunjung 'Budi' berhasil dicatat."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function storeGuest(OnsiteReadingGuestRequest $request): JsonResponse
    {
        try {
            $reading = $this->onsiteReadingService->recordGuestReading(
                (int) $request->validated('guestbook_id'),
                $request->validated('collection_code')
            );

            $reading->load(['guestbook', 'collection.catalog']);

            return response()->json([
                'success' => true,
                'message' => "Aktivitas baca di tempat untuk pengunjung '{$reading->guestbook->visitor_name}' pada buku '{$reading->collection->catalog->title}' berhasil dicatat.",
                'data'    => new OnsiteReadingResource($reading),
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

    #[OA\Get(
        path: "/admin/onsite-readings/reports",
        summary: "Rekap & Statistik Baca di Tempat",
        description: "Menampilkan rekapitulasi data dan Top Buku yang paling sering dibaca di tempat.",
        operationId: "getOnsiteReadingReports",
        tags: ["Baca di Tempat"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Laporan baca di tempat berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Laporan dan statistik baca di tempat berhasil diambil."),
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

        $reports = $this->onsiteReadingService->getReports($startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Laporan dan statistik baca di tempat berhasil diambil.',
            'data'    => [
                'summary'              => $reports['summary'],
                'top_read_collections' => $reports['top_read_collections'],
                'logs'                 => OnsiteReadingResource::collection($reports['logs'])->response()->getData(true),
            ],
        ], 200);
    }
}
