<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateCatalogRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Imports\BooksStockOpnameImport;
use App\Models\Catalog;
use App\Models\Collection;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Admin Book Management",
 *     description="API Pengelolaan Katalog Buku & Eksemplar Fisik (CRUD & Import Excel)"
 * )
 */
class BookManagementController extends Controller
{
    /**
     * Recalculate and update total_copies and available_copies for a catalog.
     */
    protected function syncCatalogCopyCounts(int $catalogId): void
    {
        $total = Collection::where('catalog_id', $catalogId)->count();
        $available = Collection::where('catalog_id', $catalogId)
            ->where('availability_status', 'AVAILABLE')
            ->where('is_quarantine', false)
            ->count();

        Catalog::where('id', $catalogId)->update([
            'total_copies'     => $total,
            'available_copies' => $available,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/books",
     *     summary="Menampilkan daftar katalog buku (Dukungan Filter, Search, & Pagination)",
     *     description="Menampilkan data katalog buku beserta relasi eksemplar koleksi fisik dengan dukungan pencarian berdasarkan judul, pengarang, ISBN, atau barcode eksemplar.",
     *     operationId="getAdminBooks",
     *     tags={"Admin Book Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", description="Kata kunci pencarian (Judul, Subjudul, Author, ISBN, Barcode)", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_quarantine", in="query", description="Filter status karantina (0 atau 1)", required=false, @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="per_page", in="query", description="Jumlah item per halaman", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar katalog buku berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar katalog buku berhasil diambil."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Catalog::with('collections');

        // Filter Pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%")
                    ->orWhere('author_main', 'like', "%{$search}%")
                    ->orWhere('publisher', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%")
                    ->orWhereHas('collections', function ($cq) use ($search) {
                        $cq->where('barcode_no', 'like', "%{$search}%")
                           ->orWhere('item_number', 'like', "%{$search}%");
                    });
            });
        }

        // Filter Karantina
        if ($request->has('is_quarantine')) {
            $isQuarantine = filter_var($request->is_quarantine, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_quarantine', $isQuarantine);
        }

        $perPage = (int) $request->input('per_page', 15);
        $catalogs = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar katalog buku berhasil diambil.',
            'data'    => $catalogs,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/books/{id}",
     *     summary="Menampilkan detail 1 katalog buku beserta daftar eksemplar fisiknya",
     *     description="Mengambil detail master data katalog beserta seluruh koleksi item eksemplarnya.",
     *     operationId="getAdminBookShow",
     *     tags={"Admin Book Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="ID Katalog Buku", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Detail katalog berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail katalog buku berhasil diambil."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Katalog buku tidak ditemukan"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $catalog = Catalog::with('collections')->find($id);

        // Fallback: Jika ID yang dikirimkan adalah ID Eksemplar (Collection), cari data katalog induknya
        if (! $catalog) {
            $collection = Collection::find($id);
            if ($collection && $collection->catalog_id) {
                $catalog = Catalog::with('collections')->find($collection->catalog_id);
            }
        }

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => "Katalog buku #{$id} tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail katalog buku berhasil diambil.',
            'data'    => $catalog,
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/books",
     *     summary="Single Entry Buku Baru atau Tambah Eksemplar Stok ke Katalog Eksisting",
     *     description="Membuat katalog buku baru sekaligus 1 eksemplar pertamanya, ATAU menambahkan stok eksemplar baru ke catalog_id yang sudah ada.",
     *     operationId="storeAdminBook",
     *     tags={"Admin Book Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="catalog_id", type="integer", nullable=true, example=null, description="Isi jika hanya menambah eksemplar baru ke katalog yang sudah ada"),
     *             @OA\Property(property="title", type="string", example="Pemrograman Web Modern dengan Laravel 12"),
     *             @OA\Property(property="publisher", type="string", example="Informatika Bandung"),
     *             @OA\Property(property="publish_year", type="string", example="2026"),
     *             @OA\Property(property="author_main", type="string", example="Angga Prabaswara"),
     *             @OA\Property(property="isbn", type="string", example="978-602-1234-56-7"),
     *             @OA\Property(property="barcode_no", type="string", example="BC-20260901-001"),
     *             @OA\Property(property="library_location_code", type="string", example="PUSDA"),
     *             @OA\Property(property="room_location_code", type="string", example="Lantai 2 - Rak A1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Buku/eksemplar berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data buku dan eksemplar berhasil disimpan."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal"
     *     )
     * )
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            if (! empty($validated['catalog_id'])) {
                // Opsi A: Tambahkan stok eksemplar baru ke catalog_id eksisting
                $catalog = Catalog::findOrFail($validated['catalog_id']);
            } else {
                // Opsi B: Buat data master catalog baru
                $catalog = Catalog::create([
                    'material_type'               => $validated['material_type'] ?? 'Monograf',
                    'media_type'                  => $validated['media_type'] ?? 'Tercetak',
                    'title'                       => $validated['title'],
                    'subtitle'                    => $validated['subtitle'] ?? null,
                    'statement_of_responsibility' => $validated['statement_of_responsibility'] ?? null,
                    'author_main'                 => $validated['author_main'] ?? null,
                    'author_corporate'            => $validated['author_corporate'] ?? null,
                    'author_additional_person'    => $validated['author_additional_person'] ?? null,
                    'author_additional_corporate' => $validated['author_additional_corporate'] ?? null,
                    'edition'                     => $validated['edition'] ?? null,
                    'publish_city'                => $validated['publish_city'] ?? null,
                    'publisher'                   => $validated['publisher'] ?? null,
                    'publish_year'                => $validated['publish_year'] ?? null,
                    'pages'                       => $validated['pages'] ?? null,
                    'dimension'                   => $validated['dimension'] ?? null,
                    'isbn'                        => $validated['isbn'] ?? null,
                    'issn'                        => $validated['issn'] ?? null,
                    'ismn'                        => $validated['ismn'] ?? null,
                    'ddc_number'                  => $validated['ddc_number'] ?? null,
                    'catalog_call_number'         => $validated['catalog_call_number'] ?? null,
                    'subject_topic'               => $validated['subject_topic'] ?? null,
                    'language'                    => $validated['language'] ?? 'Indonesia',
                    'abstract'                    => $validated['abstract'] ?? null,
                    'serial_edition'              => $validated['serial_edition'] ?? null,
                    'serial_publish_date'         => $validated['serial_publish_date'] ?? null,
                    'serial_accompanying_material'=> $validated['serial_accompanying_material'] ?? null,
                    'serial_notes'                => $validated['serial_notes'] ?? null,
                    'is_quarantine'               => $validated['is_quarantine'] ?? false,
                ]);
            }

            // Buat 1 eksemplar fisik di tabel collections
            if (empty($validated['barcode_no'])) {
                $baseBarcode = 'BC-' . date('Ymd') . '-' . str_pad((string) $catalog->id, 4, '0', STR_PAD_LEFT);
                $barcode = $baseBarcode;
                $counter = 1;
                while (Collection::where('barcode_no', $barcode)->exists()) {
                    $barcode = $baseBarcode . '-' . $counter;
                    $counter++;
                }
            } else {
                $barcode = $validated['barcode_no'];
            }

            $collection = Collection::create([
                'catalog_id'            => $catalog->id,
                'item_number'           => $validated['item_number'] ?? ('ITM-' . $catalog->id),
                'barcode_no'            => $barcode,
                'rfid_no'               => $validated['rfid_no'] ?? null,
                'exemplar_call_number'  => $validated['exemplar_call_number'] ?? $catalog->catalog_call_number,
                'procurement_date'      => $validated['procurement_date'] ?? now(),
                'source_type'           => $validated['source_type'] ?? 'Pembelian',
                'source_name'           => $validated['source_name'] ?? null,
                'currency'              => $validated['currency'] ?? 'IDR',
                'price'                 => $validated['price'] ?? 0,
                'library_location_code' => $validated['library_location_code'] ?? 'PUSDA',
                'room_location_code'    => $validated['room_location_code'] ?? 'Lantai 1',
                'access_type'           => $validated['access_type'] ?? 'Dapat Dipinjam',
                'category'              => $validated['category'] ?? 'Koleksi Umum',
                'availability_status'   => $validated['availability_status'] ?? 'AVAILABLE',
                'is_quarantine'         => $validated['collection_is_quarantine'] ?? false,
            ]);

            // Sync total & available copies di katalog
            $this->syncCatalogCopyCounts($catalog->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data buku dan eksemplar berhasil disimpan.',
                'data'    => [
                    'catalog'    => $catalog->fresh('collections'),
                    'collection' => $collection,
                ],
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan buku: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/books/{id}",
     *     summary="Memperbarui data master katalog buku",
     *     description="Memperbarui atribut master metadata buku di tabel catalogs.",
     *     operationId="updateAdminBookCatalog",
     *     tags={"Admin Book Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="ID Katalog Buku", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title", type="string", example="Pemrograman Web Modern Update"),
     *             @OA\Property(property="publisher", type="string", example="Informatika"),
     *             @OA\Property(property="publish_year", type="string", example="2026")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Master katalog berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data master katalog berhasil diperbarui."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateCatalog(UpdateCatalogRequest $request, int $id): JsonResponse
    {
        $catalog = Catalog::findOrFail($id);
        $catalog->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data master katalog berhasil diperbarui.',
            'data'    => $catalog->fresh('collections'),
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/collections/{collection_id}",
     *     summary="Memperbarui data 1 eksemplar fisik buku",
     *     description="Memperbarui data fisik spesifik 1 eksemplar di tabel collections (barcode, lokasi rak, rfid, status ketersediaan, dll).",
     *     operationId="updateAdminBookCollection",
     *     tags={"Admin Book Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="collection_id", in="path", description="ID Eksemplar Koleksi", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"barcode_no"},
     *             @OA\Property(property="barcode_no", type="string", example="BC-20260901-999"),
     *             @OA\Property(property="availability_status", type="string", example="AVAILABLE"),
     *             @OA\Property(property="room_location_code", type="string", example="Lantai 2 - Rak B3")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eksemplar koleksi berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data eksemplar koleksi berhasil diperbarui."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateCollection(UpdateCollectionRequest $request, int $collection_id): JsonResponse
    {
        $collection = Collection::findOrFail($collection_id);
        $collection->update($request->validated());

        // Sync total & available copies katalog pemilik
        $this->syncCatalogCopyCounts($collection->catalog_id);

        return response()->json([
            'success' => true,
            'message' => 'Data eksemplar koleksi berhasil diperbarui.',
            'data'    => $collection->fresh('catalog'),
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/books/{id}",
     *     summary="Menghapus data katalog beserta seluruh eksemplar fisiknya",
     *     description="Menghapus master data katalog di tabel catalogs dan otomatis menghapus seluruh eksemplar fisiknya di tabel collections.",
     *     operationId="destroyAdminBookCatalog",
     *     tags={"Admin Book Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="ID Katalog Buku", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Katalog beserta seluruh eksemplarnya berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Katalog buku beserta seluruh eksemplarnya berhasil dihapus.")
     *         )
     *     )
     * )
     */
    public function destroyCatalog(int $id): JsonResponse
    {
        $catalog = Catalog::findOrFail($id);

        DB::beginTransaction();
        try {
            $catalog->collections()->delete();
            $catalog->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Katalog buku beserta seluruh eksemplarnya berhasil dihapus.',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus katalog: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/collections/{collection_id}",
     *     summary="Menghapus 1 eksemplar fisik buku tertentu",
     *     description="Menghapus 1 item fisik koleksi buku di tabel collections dan memperbarui jumlah total/tersedia kopi katalog.",
     *     operationId="destroyAdminBookCollection",
     *     tags={"Admin Book Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="collection_id", in="path", description="ID Eksemplar Koleksi", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Eksemplar koleksi berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Eksemplar koleksi berhasil dihapus.")
     *         )
     *     )
     * )
     */
    public function destroyCollection(int $collection_id): JsonResponse
    {
        $collection = Collection::findOrFail($collection_id);
        $catalogId = $collection->catalog_id;

        DB::beginTransaction();
        try {
            $collection->delete();
            $this->syncCatalogCopyCounts($catalogId);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Eksemplar koleksi berhasil dihapus.',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus eksemplar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/books/import",
     *     summary="Import Massal Stok Opname Buku via File Excel (.xlsx, .xls, .csv)",
     *     description="Mengunggah file Excel Stock Opname.xlsx untuk diimpor secara otomatis ke database dengan fitur deduplikasi katalog.",
     *     operationId="importAdminBooksExcel",
     *     tags={"Admin Book Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     description="File Excel Stock Opname (.xlsx, .xls, .csv maks 10MB)",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Import data buku berhasil diproses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Import data buku dari Excel berhasil diproses."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_rows", type="integer", example=50),
     *                 @OA\Property(property="catalogs_created", type="integer", example=40),
     *                 @OA\Property(property="collections_created", type="integer", example=50),
     *                 @OA\Property(property="skipped_rows", type="integer", example=0)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="File tidak valid atau gagal diproses"
     *     )
     * )
     */
    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'extensions:xlsx,xls,csv', 'max:10240'],
        ], [
            'file.mimes' => 'File harus berformat xlsx, xls, atau csv.',
            'file.extensions' => 'File harus berformat xlsx, xls, atau csv.',
        ]);

        try {
            $import = new BooksStockOpnameImport();
            $result = $import->import($request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Import data buku dari Excel berhasil diproses.',
                'data'    => $result,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses file Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menampilkan daftar seluruh eksemplar fisik buku beserta relasi katalog, pencarian, dan metrik ringkasan.
     */
    public function listCollections(Request $request): JsonResponse
    {
        $query = Collection::with('catalog');

        // Search filter (across barcode, item_number, rfid_no, and catalog title, author, isbn)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('barcode_no', 'like', "%{$search}%")
                    ->orWhere('item_number', 'like', "%{$search}%")
                    ->orWhere('rfid_no', 'like', "%{$search}%")
                    ->orWhere('exemplar_call_number', 'like', "%{$search}%")
                    ->orWhereHas('catalog', function ($cq) use ($search) {
                        $cq->where('title', 'like', "%{$search}%")
                            ->orWhere('subtitle', 'like', "%{$search}%")
                            ->orWhere('author_main', 'like', "%{$search}%")
                            ->orWhere('publisher', 'like', "%{$search}%")
                            ->orWhere('isbn', 'like', "%{$search}%");
                    });
            });
        }

        // Filter status karantina vs aktif (Daftar Koleksi utama menyembunyikan koleksi/katalog yang dikarantina)
        if ($request->filled('status') && in_array($request->status, ['KARANTINA', 'QUARANTINED', 'Karantina'])) {
            $query->where(function ($q) {
                $q->where('is_quarantine', true)
                  ->orWhere('availability_status', 'QUARANTINED')
                  ->orWhereHas('catalog', function ($cq) {
                      $cq->where('is_quarantine', true);
                  });
            });
        } else {
            // Default di Daftar Koleksi: Koleksi karantina disembunyikan ("hilang bukunya")
            $query->where('is_quarantine', false)
                  ->where('availability_status', '!=', 'QUARANTINED')
                  ->whereHas('catalog', function ($cq) {
                      $cq->where('is_quarantine', false);
                  });

            if ($request->filled('status') && $request->status !== 'all' && $request->status !== 'Semua Status Eksemplar') {
                $status = $request->status;
                if ($status === 'TERSEDIA' || $status === 'AVAILABLE' || $status === 'Tersedia di Rak') {
                    $query->where('availability_status', 'AVAILABLE');
                } elseif ($status === 'DIPINJAM' || $status === 'BORROWED' || $status === 'Sedang Dipinjam') {
                    $query->where('availability_status', 'BORROWED');
                } elseif ($status === 'BINDING' || $status === 'MAINTENANCE' || $status === 'Proses Binding') {
                    $query->where('availability_status', 'MAINTENANCE');
                } else {
                    $query->where('availability_status', $status);
                }
            }
        }

        // Filter material type
        if ($request->filled('material_type') && $request->material_type !== 'all' && $request->material_type !== 'Semua Jenis Bahan') {
            $materialType = $request->material_type;
            $query->whereHas('catalog', function ($cq) use ($materialType) {
                $cq->where('material_type', 'like', "%{$materialType}%");
            });
        }

        // Filter room location
        if ($request->filled('room_location') && $request->room_location !== 'all' && $request->room_location !== 'Semua Lokasi Penyimpanan') {
            $query->where('room_location_code', 'like', "%{$request->room_location}%");
        }

        // Filter year
        if ($request->filled('year')) {
            $year = $request->year;
            $query->where(function ($q) use ($year) {
                $q->whereYear('procurement_date', $year)
                  ->orWhereHas('catalog', function ($cq) use ($year) {
                      $cq->where('publish_year', $year);
                  });
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        $collections = $query->orderBy('id', 'desc')->paginate($perPage);

        // Calculate summary metrics
        $metrics = [
            'total_exemplars'   => Collection::count(),
            'total_titles'      => Catalog::count(),
            'available_count'   => Collection::where('availability_status', 'AVAILABLE')->where('is_quarantine', false)->count(),
            'borrowed_count'    => Collection::where('availability_status', 'BORROWED')->count(),
            'maintenance_count' => Collection::where('availability_status', 'MAINTENANCE')->count(),
            'quarantine_count'  => Collection::where('is_quarantine', true)->orWhere('availability_status', 'QUARANTINED')->count(),
            'month_new_count'   => Collection::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Daftar koleksi eksemplar berhasil diambil.',
            'data'    => \App\Http\Resources\CollectionResource::collection($collections)->response()->getData(true),
            'metrics' => $metrics,
        ], 200);
    }
}
