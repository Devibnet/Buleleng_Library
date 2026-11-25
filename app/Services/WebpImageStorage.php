<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Satu pintu penyimpanan gambar unggahan: setiap gambar dikonversi ke WebP kualitas 80
 * lalu disimpan di disk "public".
 *
 * Gambar dibaca ulang lewat GD, sehingga file yang bukan gambar (atau gambar rusak) ditolak
 * dan metadata EXIF (termasuk lokasi GPS dari kamera ponsel) tidak ikut tersimpan.
 */
class WebpImageStorage
{
    public const QUALITY = 80;

    /**
     * Simpan file unggahan sebagai WebP. Mengembalikan path relatif pada disk public,
     * mis. "covers/catalog-12-ab12cd34.webp".
     *
     * @param string $field nama field form, dipakai untuk pesan galat validasi
     */
    public static function storeUploaded(UploadedFile $file, string $directory, string $prefix, string $field = 'photo'): string
    {
        $binary = @file_get_contents($file->getRealPath() ?: $file->getPathname());
        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([$field => 'File gambar tidak dapat dibaca.']);
        }

        return self::storeBinary($binary, $directory, $prefix, $field);
    }

    /**
     * Simpan isi gambar mentah (mis. hasil decode base64 dari kamera) sebagai WebP.
     */
    public static function storeBinary(string $binary, string $directory, string $prefix, string $field = 'photo'): string
    {
        $webp = self::toWebp($binary, $field);
        $path = trim($directory, '/') . '/' . $prefix . '-' . now()->format('YmdHis') . '-' . Str::random(8) . '.webp';

        if (! Storage::disk('public')->put($path, $webp)) {
            throw ValidationException::withMessages([$field => 'Gambar gagal disimpan di server.']);
        }

        return $path;
    }

    /**
     * Ubah isi gambar (JPEG/PNG/WebP/GIF/BMP) menjadi WebP kualitas 80.
     */
    public static function toWebp(string $binary, string $field = 'photo'): string
    {
        if (! function_exists('imagewebp')) {
            throw new \RuntimeException('Ekstensi GD dengan dukungan WebP belum aktif di server.');
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            throw ValidationException::withMessages([$field => 'File yang diunggah bukan gambar yang valid.']);
        }

        // Batas piksel melindungi memori server dari gambar beresolusi ekstrem (sekitar 40 MP)
        if (($info[0] ?? 0) * ($info[1] ?? 0) > 40_000_000) {
            throw ValidationException::withMessages([$field => 'Resolusi gambar terlalu besar. Gunakan gambar di bawah 40 megapiksel.']);
        }

        $image = @imagecreatefromstring($binary);
        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([$field => 'Gambar rusak atau formatnya tidak didukung.']);
        }

        if (($info[2] ?? null) === IMAGETYPE_JPEG) {
            $image = self::applyExifOrientation($image, $binary);
        }

        // Gambar ber-palet (PNG 8-bit, GIF) harus truecolor agar bisa ditulis sebagai WebP
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagewebp($image, null, self::QUALITY);
        $webp = ob_get_clean();

        if (! $ok || ! $webp) {
            throw ValidationException::withMessages([$field => 'Gambar gagal dikonversi ke WebP.']);
        }

        return $webp;
    }

    /**
     * Foto kamera ponsel sering tersimpan miring dengan penanda rotasi di EXIF.
     * Karena EXIF dibuang saat konversi, rotasinya diterapkan langsung ke piksel.
     */
    private static function applyExifOrientation(GdImage $image, string $binary): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($binary));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated instanceof GdImage) {
            return $rotated;
        }

        return $image;
    }
}
