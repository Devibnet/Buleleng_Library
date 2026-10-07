<?php

namespace App\Imports;

use App\Models\Catalog;
use App\Models\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BooksStockOpnameImport
{
    /**
     * Process and import stock opname excel file into database.
     *
     * @param UploadedFile|string $file
     * @return array
     */
    public function import(UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);

        if (empty($rows)) {
            return [
                'total_rows'           => 0,
                'catalogs_created'    => 0,
                'collections_created' => 0,
                'skipped_rows'        => 0,
                'errors'              => ['Berkas Excel kosong atau tidak terbaca.'],
            ];
        }

        // Header row (Baris 1)
        $headerRow = array_shift($rows);
        $headers = [];
        foreach ($headerRow as $col => $val) {
            if ($val !== null && trim((string) $val) !== '') {
                $headers[$col] = Str::slug(trim((string) $val), '_');
            }
        }

        $totalRows = 0;
        $catalogsCreated = 0;
        $collectionsCreated = 0;
        $skippedRows = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            $affectedCatalogIds = [];

            foreach ($rows as $rowIndex => $row) {
                // Map values with column header keys
                $rowData = [];
                foreach ($row as $col => $val) {
                    $headerName = $headers[$col] ?? null;
                    if ($headerName) {
                        $rowData[$headerName] = is_string($val) ? trim($val) : $val;
                    }
                }

                // Extrak nilai judul
                $title = $rowData['title'] ?? $rowData['judul'] ?? $rowData['judul_buku'] ?? null;
                if (! $title) {
                    $skippedRows++;
                    continue;
                }

                $totalRows++;

                $isbn = $rowData['isbn'] ?? null;
                $publisher = $rowData['publisher'] ?? $rowData['penerbit'] ?? null;
                $publishYear = $rowData['publish_year'] ?? $rowData['tahun_terbit'] ?? $rowData['tahun'] ?? null;
                $authorMain = $rowData['author'] ?? $rowData['author_main'] ?? $rowData['pengarang'] ?? $rowData['penulis'] ?? null;

                // Logika Deduplikasi Catalog: Cek berdasarkan ISBN atau (Title + Publisher + Publish Year)
                $catalog = null;
                if (! empty($isbn)) {
                    $catalog = Catalog::where('isbn', $isbn)->first();
                }

                if (! $catalog && ! empty($title)) {
                    $query = Catalog::where('title', $title);
                    if (! empty($publisher)) {
                        $query->where('publisher', $publisher);
                    }
                    if (! empty($publishYear)) {
                        $query->where('publish_year', $publishYear);
                    }
                    $catalog = $query->first();
                }

                // Buat catalog baru jika belum ada
                if (! $catalog) {
                    $catalog = Catalog::create([
                        'material_type'               => $rowData['material_type'] ?? $rowData['jenis_bahan'] ?? 'Monograf',
                        'media_type'                  => $rowData['media_type'] ?? 'Tercetak',
                        'title'                       => $title,
                        'subtitle'                    => $rowData['subtitle'] ?? $rowData['sub_judul'] ?? null,
                        'statement_of_responsibility' => $rowData['statement_of_responsibility'] ?? null,
                        'author_main'                 => $authorMain,
                        'author_corporate'            => $rowData['author_corporate'] ?? null,
                        'edition'                     => $rowData['edition'] ?? $rowData['edisi'] ?? null,
                        'publish_city'                => $rowData['publish_city'] ?? $rowData['tempat_terbit'] ?? null,
                        'publisher'                   => $publisher,
                        'publish_year'                => $publishYear,
                        'pages'                       => $rowData['pages'] ?? $rowData['halaman'] ?? null,
                        'dimension'                   => $rowData['dimension'] ?? $rowData['ukuran'] ?? null,
                        'isbn'                        => $isbn,
                        'ddc_number'                  => $rowData['ddc'] ?? $rowData['ddc_number'] ?? $rowData['no_ddc'] ?? null,
                        'catalog_call_number'         => $rowData['call_number'] ?? $rowData['catalog_call_number'] ?? $rowData['no_panggil'] ?? null,
                        'subject_topic'               => $rowData['subject'] ?? $rowData['subject_topic'] ?? $rowData['subjek'] ?? null,
                        'language'                    => $rowData['language'] ?? $rowData['bahasa'] ?? 'Indonesia',
                        'abstract'                    => $rowData['abstract'] ?? $rowData['ringkasan'] ?? null,
                        'is_quarantine'               => false,
                    ]);
                    $catalogsCreated++;
                }

                $affectedCatalogIds[$catalog->id] = true;

                // Penanganan Barcode Eksemplar
                $barcodeNo = $rowData['barcode'] ?? $rowData['barcode_no'] ?? $rowData['no_barcode'] ?? null;
                if (empty($barcodeNo) || Collection::where('barcode_no', $barcodeNo)->exists()) {
                    $barcodeNo = 'BC-' . date('Ymd') . '-' . sprintf('%05d', mt_rand(1, 99999));
                }

                // Procure Date parsing
                $procurementDate = now()->format('Y-m-d');
                $rawDate = $rowData['procurement_date'] ?? $rowData['tgl_pengadaan'] ?? $rowData['tanggal_pengadaan'] ?? null;
                if (! empty($rawDate)) {
                    $timestamp = strtotime((string) $rawDate);
                    if ($timestamp !== false) {
                        $procurementDate = date('Y-m-d', $timestamp);
                    }
                }

                // Buat data fisik eksemplar di tabel collections
                Collection::create([
                    'catalog_id'            => $catalog->id,
                    'item_number'           => $rowData['item_number'] ?? $rowData['no_induk'] ?? null,
                    'barcode_no'            => $barcodeNo,
                    'rfid_no'               => $rowData['rfid'] ?? $rowData['rfid_no'] ?? $rowData['no_rfid'] ?? null,
                    'exemplar_call_number'  => $rowData['exemplar_call_number'] ?? $rowData['no_panggil'] ?? $catalog->catalog_call_number,
                    'procurement_date'      => $procurementDate,
                    'source_type'           => $rowData['source_type'] ?? $rowData['sumber_pengadaan'] ?? 'Pembelian',
                    'source_name'           => $rowData['source_name'] ?? $rowData['asal_sumber'] ?? null,
                    'currency'              => $rowData['currency'] ?? 'IDR',
                    'price'                 => (float) ($rowData['price'] ?? $rowData['harga'] ?? 0),
                    'library_location_code' => $rowData['library_location_code'] ?? $rowData['lokasi'] ?? 'PUSDA',
                    'room_location_code'    => $rowData['room_location_code'] ?? $rowData['ruangan'] ?? 'Lantai 1',
                    'access_type'           => $rowData['access_type'] ?? 'Dapat Dipinjam',
                    'category'              => $rowData['category'] ?? $rowData['kategori'] ?? 'Koleksi Umum',
                    'availability_status'   => strtoupper($rowData['availability_status'] ?? $rowData['status'] ?? 'AVAILABLE'),
                    'is_quarantine'         => false,
                ]);
                $collectionsCreated++;
            }

            // Hitung ulang total_copies dan available_copies di tabel catalogs untuk katalog yang terpengaruh
            foreach (array_keys($affectedCatalogIds) as $catId) {
                $total = Collection::where('catalog_id', $catId)->count();
                $avail = Collection::where('catalog_id', $catId)
                    ->where('availability_status', 'AVAILABLE')
                    ->where('is_quarantine', false)
                    ->count();

                Catalog::where('id', $catId)->update([
                    'total_copies'     => $total,
                    'available_copies' => $avail,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'total_rows'           => $totalRows,
            'catalogs_created'    => $catalogsCreated,
            'collections_created' => $collectionsCreated,
            'skipped_rows'        => $skippedRows,
            'errors'              => $errors,
        ];
    }
}
