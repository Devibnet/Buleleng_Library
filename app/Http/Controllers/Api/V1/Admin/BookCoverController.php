<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\WebpImageStorage;
use App\Http\Controllers\Controller;
use App\Models\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Sampul monograf katalog. Dipisah dari simpan katalog agar payload katalog tetap JSON,
 * dan sampul diunggah setelah katalog punya ID.
 */
class BookCoverController extends Controller
{
    private const DIRECTORY = 'covers';

    /**
     * POST /api/v1/admin/books/{id}/cover   (multipart, field "cover")
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $catalog = Catalog::find($id);
        if (! $catalog) {
            return response()->json(['success' => false, 'message' => "Katalog #{$id} tidak ditemukan."], 404);
        }

        $request->validate([
            'cover' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'cover.required' => 'Pilih file gambar sampul terlebih dahulu.',
            'cover.mimes'    => 'Sampul harus berupa gambar JPG, PNG, atau WebP.',
            'cover.max'      => 'Ukuran sampul maksimal 2 MB.',
        ]);

        // Selalu disimpan sebagai WebP kualitas 80
        $path = WebpImageStorage::storeUploaded($request->file('cover'), self::DIRECTORY, 'catalog-' . $catalog->id, 'cover');

        $oldPath = $catalog->cover_path;
        $catalog->forceFill(['cover_path' => $path])->save();

        // File lama dihapus setelah path baru tersimpan, agar katalog tidak pernah menunjuk file yang hilang
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'success'   => true,
            'message'   => 'Sampul monograf berhasil disimpan.',
            'cover_url' => $catalog->cover_url,
        ]);
    }

    /**
     * DELETE /api/v1/admin/books/{id}/cover
     */
    public function destroy(int $id): JsonResponse
    {
        $catalog = Catalog::find($id);
        if (! $catalog) {
            return response()->json(['success' => false, 'message' => "Katalog #{$id} tidak ditemukan."], 404);
        }

        if ($catalog->cover_path) {
            Storage::disk('public')->delete($catalog->cover_path);
            $catalog->forceFill(['cover_path' => null])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sampul monograf dihapus.',
        ]);
    }
}
