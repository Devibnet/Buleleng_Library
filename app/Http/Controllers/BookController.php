<?php

namespace App\Http\Controllers;

use App\Http\Resources\CatalogResource;
use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
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

        // OPAC publik hanya menampilkan katalog yang TIDAK dikarantina
        $query = Catalog::where('is_quarantine', false);

        // Filter "Favorit Saya": hanya untuk anggota yang login (token Sanctum opsional di rute publik)
        if ($request->boolean('favorites')) {
            $member = auth('sanctum')->user();

            if (! $member instanceof Member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wajib Login terlebih dahulu untuk melihat Buku Favorit.',
                ], 401);
            }

            $query->whereIn('id', $member->favoriteCatalogs()->select('catalogs.id'));
        }

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
    public function stats(): JsonResponse
    {
        $today = now()->toDateString();

        $catalogIds = Catalog::where('is_quarantine', false)->select('id');

        return response()->json([
            'success' => true,
            'message' => 'Statistik koleksi perpustakaan berhasil diambil.',
            'data'    => [
                // Judul katalog & eksemplar fisik yang tampil di OPAC (tidak dikarantina)
                'total_titles'    => Catalog::where('is_quarantine', false)->count(),
                'total_copies'    => Collection::whereIn('catalog_id', $catalogIds)->where('is_quarantine', false)->count(),
                // Buku yang saat ini berada di tangan anggota
                'borrowed_now'    => Loan::whereIn('status', [Loan::STATUS_BORROWED, Loan::STATUS_OVERDUE])->count(),
                // Anggota berstatus aktif dan kartu belum kedaluwarsa
                'active_members'  => Member::where(function ($q) {
                    $q->whereNull('status')->orWhere('status', 'like', 'Aktif%');
                })->where(function ($q) use ($today) {
                    $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today);
                })->count(),
                // Transaksi peminjaman + pengembalian yang terjadi hari ini
                'circulation_today' => Loan::whereDate('loan_date', $today)->count()
                    + Loan::whereDate('return_date', $today)->count(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $catalog = Catalog::with('collections')
            ->where('is_quarantine', false)
            ->find($id);

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => "Buku dengan ID {$id} tidak ditemukan atau sedang dalam karantina.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail buku berhasil diambil.',
            'data'    => new CatalogResource($catalog),
        ], 200);
    }
}
