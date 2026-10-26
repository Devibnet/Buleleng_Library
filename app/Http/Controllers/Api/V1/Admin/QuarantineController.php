<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResource;
use App\Http\Resources\CollectionResource;
use App\Models\Catalog;
use App\Models\Collection;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuarantineController extends Controller
{
    /**
     * Recalculate and update copy counts for a catalog.
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
     * Menampilkan daftar koleksi/katalog yang sedang dikarantina dengan filter dan metrik.
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Ambil koleksi eksemplar fisik yang dikarantina
        $collectionQuery = Collection::with('catalog')
            ->where(function ($q) {
                $q->where('is_quarantine', true)
                  ->orWhere('availability_status', 'QUARANTINED');
            });

        // 2. Ambil juga katalog yang dikarantina pada level judul
        $catalogQuery = Catalog::with('collections')
            ->where('is_quarantine', true);

        // Search query
        if ($request->filled('search')) {
            $search = $request->search;
            $collectionQuery->where(function ($q) use ($search) {
                $q->where('barcode_no', 'like', "%{$search}%")
                  ->orWhere('item_number', 'like', "%{$search}%")
                  ->orWhere('quarantine_reason', 'like', "%{$search}%")
                  ->orWhereHas('catalog', function ($cq) use ($search) {
                      $cq->where('title', 'like', "%{$search}%")
                         ->orWhere('author_main', 'like', "%{$search}%")
                         ->orWhere('publisher', 'like', "%{$search}%")
                         ->orWhere('isbn', 'like', "%{$search}%");
                  });
            });

            $catalogQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author_main', 'like', "%{$search}%")
                  ->orWhere('publisher', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%")
                  ->orWhere('quarantine_reason', 'like', "%{$search}%");
            });
        }

        $quarantinedCollections = $collectionQuery->orderBy('id', 'desc')->get();
        $quarantinedCatalogs = $catalogQuery->orderBy('id', 'desc')->get();

        // Gabungkan item karantina
        $allItems = collect();

        // Tambahkan koleksi yang dikarantina
        foreach ($quarantinedCollections as $col) {
            $cat = $col->catalog;
            $allItems->push([
                'id'                   => $col->id,
                'db_id'                => $col->id,
                'is_collection'        => true,
                'collection_id'        => $col->id,
                'catalog_id'           => $col->catalog_id,
                'barcode_no'           => $col->barcode_no,
                'item_number'          => $col->item_number,
                'rfid_no'              => $col->rfid_no,
                'physical_condition'   => $col->physical_condition ?? 'Rusak',
                'quarantine_reason'    => $col->quarantine_reason ?? 'Kerusakan fisik / Karantina',
                'quarantine_notes'     => $col->quarantine_notes,
                'availability_status'  => $col->availability_status,
                'title'                => $cat?->title ?? 'Judul Tidak Diketahui',
                'subtitle'             => $cat?->subtitle,
                'author_main'          => $cat?->author_main ?? 'Penulis Tidak Diketahui',
                'edition'              => $cat?->edition ?? 'Cet. 1',
                'publisher'            => $cat?->publisher ?? 'Perpustakaan',
                'publish_city'         => $cat?->publish_city ?? '',
                'publish_year'         => $cat?->publish_year ?? '2026',
                'pages'                => $cat?->pages ?? '-',
                'dimension'            => $cat?->dimension ?? '',
                'isbn'                 => $cat?->isbn ?? '-',
                'ddc_number'           => $cat?->ddc_number ?? '',
                'catalog_call_number'  => $col->exemplar_call_number ?: ($cat?->catalog_call_number ?? ''),
                'subject_topic'        => $cat?->subject_topic ?? 'Koleksi Umum',
                'abstract'             => $col->quarantine_notes ?: ($cat?->abstract ?? ''),
                'is_rda'               => true,
                'total_copies'         => $cat?->total_copies ?? 1,
                'available_copies'     => $cat?->available_copies ?? 0,
                'collections'          => $cat ? CollectionResource::collection($cat->collections) : [],
            ]);
        }

        // Tambahkan katalog yang dikarantina langsung (jika seluruh judul dikarantina dan belum tercakup per eksemplar)
        foreach ($quarantinedCatalogs as $cat) {
            // Hindari duplikasi jika seluruh eksemplarnya sudah masuk list
            $hasCollectionListed = $quarantinedCollections->contains('catalog_id', $cat->id);
            if (!$hasCollectionListed) {
                $allItems->push([
                    'id'                   => $cat->id,
                    'db_id'                => $cat->id,
                    'is_collection'        => false,
                    'collection_id'        => null,
                    'catalog_id'           => $cat->id,
                    'barcode_no'           => null,
                    'item_number'          => null,
                    'rfid_no'              => null,
                    'physical_condition'   => 'Rusak',
                    'quarantine_reason'    => $cat->quarantine_reason ?? 'Data katalog dikarantina',
                    'quarantine_notes'     => null,
                    'availability_status'  => 'QUARANTINED',
                    'title'                => $cat->title,
                    'subtitle'             => $cat->subtitle,
                    'author_main'          => $cat->author_main ?? 'Penulis Tidak Diketahui',
                    'edition'              => $cat->edition ?? 'Cet. 1',
                    'publisher'            => $cat->publisher ?? 'Perpustakaan',
                    'publish_city'         => $cat->publish_city ?? '',
                    'publish_year'         => $cat->publish_year ?? '2026',
                    'pages'                => $cat->pages ?? '-',
                    'dimension'            => $cat->dimension ?? '',
                    'isbn'                 => $cat->isbn ?? '-',
                    'ddc_number'           => $cat->ddc_number ?? '',
                    'catalog_call_number'  => $cat->catalog_call_number ?? '',
                    'subject_topic'        => $cat->subject_topic ?? 'Koleksi Umum',
                    'abstract'             => $cat->abstract ?? '',
                    'is_rda'               => true,
                    'total_copies'         => $cat->total_copies,
                    'available_copies'     => $cat->available_copies,
                    'collections'          => CollectionResource::collection($cat->collections),
                ]);
            }
        }

        // Filter category jika ada
        if ($request->filled('category') && $request->category !== 'all') {
            $cat = $request->category;
            $allItems = $allItems->filter(function ($item) use ($cat) {
                $reason = strtolower($item['quarantine_reason'] ?? '');
                if ($cat === 'duplikasi') {
                    return str_contains($reason, 'duplikasi');
                } elseif ($cat === 'dihapus_sementara') {
                    return str_contains($reason, 'hapus') || str_contains($reason, 'write-off');
                } elseif ($cat === 'data_belum_lengkap') {
                    return empty($item['ddc_number']) || $item['ddc_number'] === '-' || str_contains($reason, 'belum') || str_contains($reason, 'rusak');
                }
                return true;
            })->values();
        }

        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);
        $paginated = $allItems->slice(($page - 1) * $perPage, $perPage)->values();

        // Calculate metrics
        $totalQuarantine = Collection::where('is_quarantine', true)
            ->orWhere('availability_status', 'QUARANTINED')
            ->count() + Catalog::where('is_quarantine', true)->count();

        $duplicates = Collection::where('quarantine_reason', 'like', '%duplikasi%')->count()
            + Catalog::where('quarantine_reason', 'like', '%duplikasi%')->count();

        $temporaryDeleted = Collection::where(function ($q) {
            $q->where('quarantine_reason', 'like', '%hapus%')
              ->orWhere('quarantine_reason', 'like', '%write-off%');
        })->count() + Catalog::where(function ($q) {
            $q->where('quarantine_reason', 'like', '%hapus%')
              ->orWhere('quarantine_reason', 'like', '%write-off%');
        })->count();

        $incompleteData = Collection::where(function ($q) {
            $q->where('quarantine_reason', 'like', '%belum%')
              ->orWhere('quarantine_reason', 'like', '%rusak%');
        })->count() + Catalog::where(function ($q) {
            $q->whereNull('ddc_number')
              ->orWhere('ddc_number', '')
              ->orWhere('ddc_number', '-')
              ->orWhere('quarantine_reason', 'like', '%belum%')
              ->orWhere('quarantine_reason', 'like', '%rusak%');
        })->count();

        return response()->json([
            'success' => true,
            'message' => 'Daftar katalog & eksemplar karantina berhasil diambil.',
            'data'    => [
                'data'         => $paginated,
                'total'        => $allItems->count(),
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($allItems->count() / max(1, $perPage)),
            ],
            'metrics' => [
                'total_quarantine'   => $totalQuarantine,
                'duplicates'         => $duplicates,
                'incomplete_data'    => $incompleteData,
                'temporary_deleted'  => $temporaryDeleted,
            ],
        ], 200);
    }

    /**
     * Memindahkan koleksi atau katalog ke keranjang karantina.
     */
    public function moveToQuarantine(Request $request): JsonResponse
    {
        $request->validate([
            'collection_ids'     => ['nullable', 'array'],
            'catalog_ids'        => ['nullable', 'array'],
            'items'              => ['nullable', 'array'],
            'reason'             => ['required', 'string', 'max:255'],
            'physical_condition' => ['nullable', 'string'],
            'category'           => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
        ]);

        $reason = $request->reason;
        if ($request->filled('notes')) {
            $reason .= ' — ' . $request->notes;
        }

        $physicalCondition = $request->input('physical_condition', 'Rusak');

        DB::beginTransaction();
        try {
            $catalogIdsToSync = [];

            // 1. Tangani koleksi spesifik (HANYA eksemplar terpilih yang masuk karantina)
            $collectionIds = $request->collection_ids ?? [];
            if (!empty($request->items)) {
                foreach ($request->items as $it) {
                    if (is_array($it)) {
                        if (!empty($it['collection_id'])) $collectionIds[] = $it['collection_id'];
                        elseif (!empty($it['id'])) $collectionIds[] = $it['id'];
                    } elseif (is_numeric($it)) {
                        $collectionIds[] = $it;
                    }
                }
            }
            $collectionIds = array_unique(array_filter($collectionIds));

            if (!empty($collectionIds)) {
                $collections = Collection::whereIn('id', $collectionIds)->get();
                foreach ($collections as $col) {
                    $col->update([
                        'is_quarantine'       => true,
                        'availability_status' => 'QUARANTINED',
                        'physical_condition'  => $physicalCondition,
                        'quarantine_reason'   => $reason,
                        'quarantine_notes'    => $request->notes,
                    ]);

                    $catalogIdsToSync[] = $col->catalog_id;
                }
            }

            // 2. Tangani katalog langsung (hanya jika memang ditargetkan di level katalog secara eksplisit)
            $catalogIds = $request->catalog_ids ?? [];
            if (!empty($catalogIds) && empty($collectionIds)) {
                $catalogs = Catalog::whereIn('id', $catalogIds)->get();
                foreach ($catalogs as $cat) {
                    $cat->update([
                        'is_quarantine'     => true,
                        'quarantine_reason' => $reason,
                    ]);
                    // Karantina seluruh eksemplarnya juga
                    Collection::where('catalog_id', $cat->id)->update([
                        'is_quarantine'       => true,
                        'availability_status' => 'QUARANTINED',
                        'physical_condition'  => $physicalCondition,
                        'quarantine_reason'   => $reason,
                        'quarantine_notes'    => $request->notes,
                    ]);
                    $catalogIdsToSync[] = $cat->id;
                }
            }

            // Sync copy counts untuk semua katalog yang terdampak
            foreach (array_unique($catalogIdsToSync) as $catId) {
                $catalog = Catalog::find($catId);
                $catalog?->recalculateCopies();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Koleksi berhasil dipindahkan ke keranjang karantina.',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memindahkan ke karantina: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memulihkan / Menerbitkan katalog atau eksemplar dari karantina kembali ke OPAC publik.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Cek jika ID adalah ID eksemplar koleksi
            $collection = Collection::with('catalog')->find($id);
            if ($collection && ($collection->is_quarantine || $collection->availability_status === 'QUARANTINED')) {
                $collection->update([
                    'is_quarantine'       => false,
                    'availability_status' => 'AVAILABLE',
                    'physical_condition'  => $request->input('physical_condition', 'Baik'),
                    'quarantine_reason'   => null,
                    'quarantine_notes'    => null,
                ]);

                if ($collection->catalog) {
                    if ($collection->catalog->is_quarantine) {
                        $collection->catalog->update([
                            'is_quarantine'     => false,
                            'quarantine_reason' => null,
                        ]);
                    }
                    $collection->catalog->recalculateCopies();
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Eksemplar {$collection->barcode_no} berhasil dipulihkan ke status Tersedia!",
                    'data'    => new CollectionResource($collection->fresh('catalog')),
                ], 200);
            }

            // Jika ID adalah ID Katalog
            $catalog = Catalog::with('collections')->find($id);

            if (!$catalog) {
                return response()->json([
                    'success' => false,
                    'message' => "Katalog / Eksemplar #{$id} tidak ditemukan.",
                ], 404);
            }

            $updateData = [
                'is_quarantine'     => false,
                'quarantine_reason' => null,
            ];

            if ($request->filled('ddc_number')) {
                $updateData['ddc_number'] = $request->ddc_number;
            }
            if ($request->filled('catalog_call_number')) {
                $updateData['catalog_call_number'] = $request->catalog_call_number;
            }
            if ($request->filled('abstract')) {
                $updateData['abstract'] = $request->abstract;
            }

            $catalog->update($updateData);

            // Pulihkan eksemplar fisik katalog ini
            Collection::where('catalog_id', $catalog->id)
                ->where(function ($q) {
                    $q->where('is_quarantine', true)
                      ->orWhere('availability_status', 'QUARANTINED');
                })
                ->update([
                    'is_quarantine'       => false,
                    'availability_status' => 'AVAILABLE',
                    'physical_condition'  => 'Baik',
                    'quarantine_reason'   => null,
                    'quarantine_notes'    => null,
                ]);

            if (!empty($catalog->catalog_call_number)) {
                Collection::where('catalog_id', $catalog->id)
                    ->where(function ($q) {
                        $q->whereNull('exemplar_call_number')
                          ->orWhere('exemplar_call_number', '');
                    })
                    ->update([
                        'exemplar_call_number' => $catalog->catalog_call_number,
                    ]);
            }

            $catalog->recalculateCopies();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Katalog \"{$catalog->title}\" berhasil diterbitkan ke OPAC Publik!",
                'data'    => new CatalogResource($catalog->fresh('collections')),
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan ke OPAC: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memulihkan massal katalog atau eksemplar terpilih ke OPAC publik.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
        ]);

        $ids = $request->ids;

        DB::beginTransaction();
        try {
            $collections = Collection::whereIn('id', $ids)->get();
            $catalogs = Catalog::whereIn('id', $ids)->get();
            $catalogsToSync = [];

            if ($collections->isNotEmpty()) {
                foreach ($collections as $col) {
                    $col->update([
                        'is_quarantine'       => false,
                        'availability_status' => 'AVAILABLE',
                        'physical_condition'  => 'Baik',
                        'quarantine_reason'   => null,
                        'quarantine_notes'    => null,
                    ]);
                    $catalogsToSync[] = $col->catalog_id;
                }
            }

            if ($catalogs->isNotEmpty()) {
                foreach ($catalogs as $cat) {
                    $cat->update([
                        'is_quarantine'     => false,
                        'quarantine_reason' => null,
                    ]);
                    Collection::where('catalog_id', $cat->id)->update([
                        'is_quarantine'       => false,
                        'availability_status' => 'AVAILABLE',
                        'physical_condition'  => 'Baik',
                        'quarantine_reason'   => null,
                        'quarantine_notes'    => null,
                    ]);
                    $catalogsToSync[] = $cat->id;
                }
            }

            foreach (array_unique($catalogsToSync) as $catId) {
                $cat = Catalog::find($catId);
                $cat?->recalculateCopies();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' item berhasil dipulihkan ke status Tersedia / OPAC Publik!',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan massal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus massal katalog atau eksemplar karantina permanen.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
        ]);

        $ids = $request->ids;

        DB::beginTransaction();
        try {
            $collections = Collection::whereIn('id', $ids)->get();
            $catalogsToSync = [];

            foreach ($collections as $col) {
                $catalogsToSync[] = $col->catalog_id;
                $col->delete();
            }

            Collection::whereIn('catalog_id', $ids)->delete();
            Catalog::whereIn('id', $ids)->delete();

            foreach (array_unique($catalogsToSync) as $catId) {
                $cat = Catalog::find($catId);
                $cat?->recalculateCopies();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' item karantina berhasil dihapus permanen.',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus massal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validasi metadata katalog karantina & simpan call number/DDC lalu terbitkan ke OPAC.
     */
    public function validateCatalog(Request $request, int $id): JsonResponse
    {
        return $this->restore($request, $id);
    }
}
