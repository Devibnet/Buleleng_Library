<?php

namespace App\Http\Controllers;

use App\Http\Resources\CatalogResource;
use App\Models\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Public Catalog",
    description: "API Katalog Publik Buku (Dapat diakses secara bebas tanpa login)"
)]
class BookController extends Controller
{
    #[OA\Get(
        path: "/api/books",
        summary: "List katalog buku & pagination",
        description: "Menampilkan katalog publik seluruh koleksi buku beserta pencarian dan paginasi.",
        operationId: "getPublicBooks",
        tags: ["Public Catalog"],
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Kata kunci pencarian judul, pengarang, penerbit, atau ISBN",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                description: "Halaman paginasi",
                required: false,
                schema: new OA\Schema(type: "integer", default: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Katalog publik buku berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Katalog publik buku berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $query = Catalog::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author_main', 'like', "%{$search}%")
                  ->orWhere('publisher', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        $catalogs = $query->orderBy('id', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Katalog publik buku berhasil diambil.',
            'data'    => CatalogResource::collection($catalogs)->response()->getData(true),
        ], 200);
    }

    #[OA\Get(
        path: "/api/books/{id}",
        summary: "Detail buku & stok available_copies",
        description: "Menampilkan rincian detail metadata buku beserta sisa ketersediaan stok (available_copies).",
        operationId: "getPublicBookById",
        tags: ["Public Catalog"],
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
                response: 200,
                description: "Detail buku berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Detail buku berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Buku tidak ditemukan",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Buku dengan ID 999 tidak ditemukan.")
                    ]
                )
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $catalog = Catalog::with(['collections' => function ($q) {
            $q->where('is_quarantine', false);
        }])->find($id);

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => "Buku dengan ID {$id} tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail buku berhasil diambil.',
            'data'    => new CatalogResource($catalog),
        ], 200);
    }
}
