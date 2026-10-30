<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Imports\BooksStockOpnameImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Import Excel bertahap dengan progress:
 * 1) prepare  : unggah & baca file, simpan baris valid per blok ke storage sementara
 * 2) process  : simpan satu blok ke database (dipanggil berulang oleh frontend untuk progress bar)
 * 3) cancel   : hapus sisa blok bila admin menghentikan import
 *
 * Diproses per request kecil agar server pengembangan (php artisan serve, satu request sekaligus)
 * tetap bisa melayani halaman lain di sela-sela blok.
 */
class BookImportController extends Controller
{
    private const CHUNK_SIZE = 500;

    private const STORAGE_DIR = 'imports';

    /**
     * POST /api/v1/admin/books/import/prepare  (multipart: file)
     */
    public function prepare(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'extensions:xlsx,xls,csv', 'max:20480'],
        ], [
            'file.mimes'      => 'File harus berformat xlsx, xls, atau csv.',
            'file.extensions' => 'File harus berformat xlsx, xls, atau csv.',
            'file.max'        => 'Ukuran file maksimal 20 MB.',
        ]);

        $this->purgeStaleImports();

        try {
            $prepared = (new BooksStockOpnameImport())->prepare($request->file('file'));
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file Excel: ' . $e->getMessage(),
            ], 422);
        }

        $summary = collect($prepared)->except('rows')->all();

        if ($prepared['total_rows'] === 0) {
            return response()->json([
                'success' => false,
                'message' => $prepared['errors'][0] ?? 'Tidak ada baris data buku yang bisa diimpor dari file ini.',
                'data'    => $summary,
            ], 422);
        }

        $token = (string) Str::uuid();
        $chunks = array_chunk($prepared['rows'], self::CHUNK_SIZE);

        foreach ($chunks as $index => $rows) {
            Storage::disk('local')->put($this->chunkPath($token, $index), json_encode($rows));
        }
        Storage::disk('local')->put($this->metaPath($token), json_encode([
            'admin_id'     => $request->user()?->id,
            'total_rows'   => $prepared['total_rows'],
            'total_chunks' => count($chunks),
            'created_at'   => now()->toIso8601String(),
        ]));

        return response()->json([
            'success' => true,
            'message' => "File berhasil dibaca: {$prepared['total_rows']} baris buku siap diimpor.",
            'data'    => array_merge($summary, [
                'token'        => $token,
                'chunk_size'   => self::CHUNK_SIZE,
                'total_chunks' => count($chunks),
            ]),
        ]);
    }

    /**
     * POST /api/v1/admin/books/import/{token}/process  { chunk: 0..n-1 }
     */
    public function process(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate(['chunk' => 'required|integer|min:0']);

        $meta = $this->loadMeta($request, $token);
        if ($meta instanceof JsonResponse) {
            return $meta;
        }

        $chunk = (int) $validated['chunk'];
        $path = $this->chunkPath($token, $chunk);

        if ($chunk >= $meta['total_chunks'] || ! Storage::disk('local')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Blok data import tidak ditemukan atau sudah diproses.',
            ], 404);
        }

        $rows = json_decode(Storage::disk('local')->get($path), true) ?? [];

        try {
            $result = (new BooksStockOpnameImport())->processRows($rows);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan blok data: ' . $e->getMessage(),
            ], 500);
        }

        // Blok yang sudah disimpan dihapus agar tidak bisa terproses dua kali
        Storage::disk('local')->delete($path);

        $done = $chunk + 1 >= $meta['total_chunks'];
        if ($done) {
            Storage::disk('local')->deleteDirectory($this->dir($token));
        }

        return response()->json([
            'success' => true,
            'message' => $done ? 'Import selesai.' : 'Blok berhasil disimpan.',
            'data'    => array_merge($result, [
                'chunk'        => $chunk,
                'total_chunks' => $meta['total_chunks'],
                'done'         => $done,
            ]),
        ]);
    }

    /**
     * DELETE /api/v1/admin/books/import/{token}
     */
    public function cancel(Request $request, string $token): JsonResponse
    {
        $meta = $this->loadMeta($request, $token);
        if ($meta instanceof JsonResponse) {
            return $meta;
        }

        Storage::disk('local')->deleteDirectory($this->dir($token));

        return response()->json([
            'success' => true,
            'message' => 'Import dihentikan. Data yang sudah tersimpan sebelumnya tidak dibatalkan.',
        ]);
    }

    private function loadMeta(Request $request, string $token): array|JsonResponse
    {
        if (! Str::isUuid($token) || ! Storage::disk('local')->exists($this->metaPath($token))) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi import tidak ditemukan atau sudah kedaluwarsa. Silakan unggah ulang file.',
            ], 404);
        }

        $meta = json_decode(Storage::disk('local')->get($this->metaPath($token)), true);

        if (($meta['admin_id'] ?? null) !== $request->user()?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi import ini milik akun admin lain.',
            ], 403);
        }

        return $meta;
    }

    /**
     * Hapus sesi import yang ditinggalkan lebih dari 1 hari.
     */
    private function purgeStaleImports(): void
    {
        foreach (Storage::disk('local')->directories(self::STORAGE_DIR) as $directory) {
            $metaFile = "{$directory}/meta.json";
            $lastModified = Storage::disk('local')->exists($metaFile)
                ? Storage::disk('local')->lastModified($metaFile)
                : 0;

            if ($lastModified < now()->subDay()->getTimestamp()) {
                Storage::disk('local')->deleteDirectory($directory);
            }
        }
    }

    private function dir(string $token): string
    {
        return self::STORAGE_DIR . "/{$token}";
    }

    private function metaPath(string $token): string
    {
        return $this->dir($token) . '/meta.json';
    }

    private function chunkPath(string $token, int $index): string
    {
        return $this->dir($token) . "/chunk_{$index}.json";
    }
}
