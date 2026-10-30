<?php

namespace App\Imports;

use App\Models\Catalog;
use App\Models\Collection;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use SimpleXMLElement;
use Throwable;
use XMLReader;
use ZipArchive;

/**
 * Import katalog & eksemplar dari Excel.
 * Mendukung format daftar buku DAPD Buleleng / INLIS (JUDUL UTAMA, TAJUK PENGARANG, NO. BARCODE, EKS, ...)
 * sekaligus format template lama ATLAS (title, judul, pengarang, barcode, ...).
 */
class BooksStockOpnameImport
{
    /**
     * Nama kolom (slug) yang dikenali untuk setiap field. Kolom yang namanya kembar di template DAPD
     * (TAJUK PENGARANG, PENGARANG, NOMOR PANGGIL) dibedakan berdasarkan urutan kemunculannya.
     */
    private const COLUMN_ALIASES = [
        'no'                           => ['no', 'nomor'],
        'procurement_date'             => ['tgl_pengadaan', 'tanggal_pengadaan', 'procurement_date'],
        'item_number'                  => ['no_induk', 'nomor_induk', 'item_number'],
        'barcode_no'                   => ['no_barcode', 'nomor_barcode', 'barcode', 'barcode_no'],
        'rfid_no'                      => ['no_rfid', 'rfid', 'rfid_no'],
        'source_type'                  => ['jenis_sumber', 'sumber_pengadaan', 'source_type'],
        'source_name'                  => ['nama_sumber', 'asal_sumber', 'source_name'],
        'currency'                     => ['mata_uang', 'currency'],
        'price'                        => ['harga', 'price'],
        'library_location_code'        => ['kode_lokasi', 'kode_lokasi_perpustakaan', 'lokasi', 'library_location_code'],
        'room_location_code'           => ['kode', 'kode_ruang', 'kode_lokasi_ruang', 'ruangan', 'room_location_code'],
        'access_type'                  => ['akses', 'access_type'],
        'category'                     => ['kategori', 'category'],
        'media_type'                   => ['media', 'media_type'],
        'availability'                 => ['ketersediaan', 'status', 'availability_status'],
        'call_number'                  => ['nomor_panggil', 'no_panggil', 'call_number'],
        'exemplar_call_number'         => ['nomor_panggil_eksemplar', 'exemplar_call_number'],
        'catalog_call_number'          => ['nomor_panggil_katalog', 'catalog_call_number'],
        'material_type'                => ['jenis_bahan', 'material_type'],
        'title'                        => ['judul_utama', 'judul', 'judul_buku', 'title'],
        'subtitle'                     => ['anak_judul', 'sub_judul', 'subtitle'],
        'statement_of_responsibility'  => ['pernyataan', 'pernyataan_tanggung_jawab', 'pernyataan_tanggungjawab', 'statement_of_responsibility'],
        'author_heading'               => ['tajuk_pengarang'],
        'author'                       => ['pengarang', 'penulis', 'author', 'author_main'],
        'author_corporate'             => ['author_corporate', 'badan_korporasi', 'tajuk_pengarang_badan_korporasi', 'tajuk_pengarang_badan_kooperasi'],
        'author_added_person'          => ['pengarang_tambahan_nama_orang', 'pengarang_tambahan_orang'],
        'author_added_corporate'       => ['pengarang_tambahan_nama_badan', 'pengarang_tambahan_badan'],
        'edition'                      => ['edisi', 'edition'],
        'publish_city'                 => ['kota_terbit', 'tempat_terbit', 'publish_city'],
        'publisher'                    => ['penerbit', 'publisher'],
        'publish_year'                 => ['tahun', 'tahun_terbit', 'publish_year'],
        'pages'                        => ['jumlah', 'jumlah_halaman', 'halaman', 'pages'],
        'dimension'                    => ['dimensi', 'ukuran', 'dimension'],
        'isbn'                         => ['isbn'],
        'issn'                         => ['issn'],
        'ismn'                         => ['ismn'],
        'ddc_number'                   => ['no_ddc', 'ddc', 'ddc_number'],
        'abstract'                     => ['abstrak', 'ringkasan', 'abstract'],
        'language'                     => ['bahasa', 'language'],
        'subject_topic'                => ['subjek_topik', 'subjek', 'subject', 'subject_topic'],
        'serial_edition'               => ['edisi_serial', 'serial_edition'],
        'serial_publish_date'          => ['tgl_terbit_edisi', 'tgl_terbit_edisi_serial', 'serial_publish_date'],
        'serial_accompanying_material' => ['bahan_sertaan', 'bahan_sertaan_serial', 'serial_accompanying_material'],
        'serial_notes'                 => ['keterangan_lain', 'keterangan_lain_serial', 'serial_notes'],
        'copies'                       => ['eks', 'eksemplar', 'jumlah_eksemplar', 'copies'],
    ];

    private const LANGUAGE_CODES = [
        'ind' => 'Indonesia', 'eng' => 'Inggris', 'ban' => 'Bali', 'jav' => 'Jawa',
        'ara' => 'Arab', 'jpn' => 'Jepang', 'chi' => 'Mandarin', 'zho' => 'Mandarin', 'dut' => 'Belanda',
    ];

    private const MAX_COPIES_PER_ROW = 100;

    private const MAX_REPORTED_MESSAGES = 50;

    /** Jumlah baris yang dibaca & di-commit ke database per blok */
    private const CHUNK_ROWS = 1000;

    /** Jumlah baris awal tiap sheet yang diperiksa untuk mencari judul kolom */
    private const SNIFF_ROWS = 20;

    /** Baris judul kolom minimal memuat judul + 3 kolom data buku lain */
    private const MIN_HEADER_FIELDS = 4;

    /** Peta huruf kolom Excel => [field, urutan kemunculan] */
    private array $columnMap = [];

    /**
     * Import sekaligus (baca file lalu simpan semua baris dalam satu proses).
     *
     * @param UploadedFile|string $file
     */
    public function import(UploadedFile|string $file): array
    {
        $prepared = $this->prepare($file);
        $processed = $this->processRows($prepared['rows']);

        foreach ($prepared['sheets'] as &$sheet) {
            $sheet['collections_created'] = $processed['sheet_collections'][$sheet['name']] ?? 0;
        }
        unset($sheet);

        return [
            'detected_format'     => $prepared['detected_format'],
            'total_rows'          => $prepared['total_rows'],
            'catalogs_created'    => $processed['catalogs_created'],
            'catalogs_matched'    => $processed['catalogs_matched'],
            'collections_created' => $processed['collections_created'],
            'skipped_rows'        => $prepared['skipped_rows'],
            'duplicate_barcodes'  => $processed['duplicate_barcodes'],
            'invalid_titles'      => $prepared['invalid_titles'],
            'sheets'              => $prepared['sheets'],
            'errors'              => array_slice(array_merge($prepared['errors'], $processed['errors']), 0, self::MAX_REPORTED_MESSAGES),
            'warnings'            => array_slice(array_merge($prepared['warnings'], $processed['warnings']), 0, self::MAX_REPORTED_MESSAGES),
        ];
    }

    /**
     * Tahap 1: baca sheet daftar buku & kumpulkan baris buku yang valid (belum menyentuh database).
     * Hasil 'rows' bisa disimpan sementara lalu diproses bertahap dengan processRows().
     *
     * Rumus Excel TIDAK dihitung ulang: dipakai hasil terakhir yang tersimpan di file. File DAPD berisi
     * puluhan ribu rumus (VLOOKUP/COUNTIF/FILTER) yang bisa membuat perhitungan ulang berjalan berjam-jam.
     *
     * @param UploadedFile|string $file
     */
    public function prepare(UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if ($this->memoryLimitBytes() < 1024 * 1024 * 1024) {
            @ini_set('memory_limit', '1024M');
        }
        @set_time_limit(0);

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        // Jangan muat sel kosong yang hanya berisi format/warna
        $reader->setReadEmptyCells(false);

        // CSV: baca semua isian sebagai teks agar DDC "005.13" / nomor panggil "000" tidak berubah jadi angka
        if ($reader instanceof CsvReader) {
            $reader->setValueBinder(new StringValueBinder());
        }

        $result = [
            'detected_format' => null,
            'total_rows'      => 0,
            'skipped_rows'    => 0,
            'invalid_titles'  => 0,
            'sheets'          => [],
            'errors'          => [],
            'warnings'        => [],
            'rows'            => [],
        ];

        // 1) Intip baris awal setiap sheet untuk memilih sheet daftar buku (XLSX: langsung dari XML, tanpa memuat file)
        if ($reader instanceof XlsxReader && class_exists(ZipArchive::class)) {
            $samples = $this->sniffXlsxSheets($filePath, self::SNIFF_ROWS);
            $spreadsheet = null;
        } else {
            $spreadsheet = $reader->load($filePath);
            $samples = [];
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $samples[$worksheet->getTitle()] = $this->sampleWorksheet($worksheet, self::SNIFF_ROWS);
            }
        }

        $plans = [];
        foreach ($samples as $sheetName => $sample) {
            $plan = $this->planSheet($sample);

            if (isset($plan['skip_reason'])) {
                $result['sheets'][] = [
                    'name'                => $sheetName,
                    'status'              => 'skipped',
                    'reason'              => $plan['skip_reason'],
                    'rows'                => 0,
                    'collections_created' => 0,
                ];
                continue;
            }

            $plans[$sheetName] = $plan;
        }

        // 2) Muat hanya sheet daftar buku (sheet rekap/pencarian besar tidak ikut dimuat)
        if ($plans && ! $spreadsheet) {
            $reader->setLoadSheetsOnly(array_map('strval', array_keys($plans)));
            $spreadsheet = $reader->load($filePath);
        }

        $multipleSheets = count($samples) > 1;
        $formats = [];

        foreach ($plans as $sheetName => $plan) {
            $worksheet = $spreadsheet->getSheetByName((string) $sheetName);
            if (! $worksheet) {
                continue;
            }

            $this->columnMap = $plan['column_map'];
            $formats[] = $plan['format'];

            $highestRow = $worksheet->getHighestDataRow();
            $sheetRows = 0;
            $prefix = $multipleSheets ? "Sheet \"{$sheetName}\" baris" : 'Baris';

            for ($chunkStart = $plan['header_row'] + 1; $chunkStart <= $highestRow; $chunkStart += self::CHUNK_ROWS) {
                [$chunkRows, $chunkRawRows] = $this->readRange(
                    $worksheet,
                    $chunkStart,
                    min($chunkStart + self::CHUNK_ROWS - 1, $highestRow),
                    $plan['last_column']
                );

                foreach ($chunkRows as $rowIndex => $row) {
                    $data = $this->mapRow($row, $chunkRawRows[$rowIndex] ?? []);
                    $title = $this->cleanIsbd($this->text($data, 'title'));
                    $rowNo = $this->text($data, 'no');

                    // Lewati baris kosong & baris contoh pada template ("Contoh")
                    if (! $title || ($rowNo && Str::lower($rowNo) === 'contoh')) {
                        $result['skipped_rows']++;
                        continue;
                    }

                    // Judul tanpa huruf (mis. "15,602") berasal dari baris rekap/jumlah, bukan data buku
                    if (! preg_match('/\p{L}/u', $title)) {
                        $result['skipped_rows']++;
                        $result['invalid_titles']++;
                        continue;
                    }

                    // Format kolom pengarang ikut disimpan per baris karena baris bisa diproses di request lain
                    $data['_has_heading'] = $plan['has_heading'];

                    $result['rows'][] = [
                        'sheet' => (string) $sheetName,
                        'title' => $title,
                        'label' => "{$prefix} {$rowIndex} (\"" . Str::limit($title, 40) . '")',
                        'data'  => $data,
                    ];
                    $sheetRows++;
                }
            }

            $result['sheets'][] = [
                'name'                => (string) $sheetName,
                'status'              => 'imported',
                'reason'              => null,
                'rows'                => $sheetRows,
                'collections_created' => 0,
            ];
        }

        $spreadsheet?->disconnectWorksheets();

        // Urutkan ringkasan sheet sesuai urutan di file
        $order = array_flip(array_map('strval', array_keys($samples)));
        usort($result['sheets'], fn ($a, $b) => ($order[$a['name']] ?? 0) <=> ($order[$b['name']] ?? 0));

        $result['total_rows'] = count($result['rows']);
        $result['detected_format'] = $formats ? implode(', ', array_unique($formats)) : null;

        if ($result['invalid_titles'] > 0) {
            $result['warnings'][] = "{$result['invalid_titles']} baris dilewati karena kolom judul hanya berisi angka (kemungkinan baris rekap/jumlah, bukan data buku).";
        }

        if (! $formats) {
            $result['errors'][] = 'Tidak ada sheet daftar buku yang dikenali. Pastikan sheet memiliki kolom "JUDUL UTAMA" beserta kolom data buku lain (pengarang, penerbit, no. induk, dll.).';
        }

        return $result;
    }

    /**
     * Tentukan apakah sheet adalah daftar buku: punya baris judul kolom yang lengkap
     * dan kolom judulnya berisi data asli (bukan hasil rumus pencarian/salinan sheet lain).
     *
     * @param array{values: array, formulas: array} $sample
     */
    private function planSheet(array $sample): array
    {
        $headerRow = $this->detectHeaderRow($sample['values']);
        if ($headerRow === null) {
            return ['skip_reason' => 'Bukan daftar buku (tidak ada kolom JUDUL UTAMA beserta kolom data buku lainnya)'];
        }

        $titleColumn = null;
        foreach ($this->columnMap as $column => [$field, $occurrence]) {
            if ($field === 'title' && $occurrence === 0) {
                $titleColumn = $column;
            }
        }

        $formulaTitles = 0;
        $literalTitles = 0;
        foreach ($sample['values'] as $rowIndex => $cells) {
            if ($rowIndex <= $headerRow || ! isset($cells[$titleColumn])) {
                continue;
            }
            if (isset($sample['formulas'][$rowIndex][$titleColumn])) {
                $formulaTitles++;
            } else {
                $literalTitles++;
            }
        }

        if ($formulaTitles >= 3 && $formulaTitles > $literalTitles) {
            return ['skip_reason' => 'Berisi hasil rumus (salinan/pencarian dari sheet lain), dilewati agar tidak dobel'];
        }

        $hasHeading = $this->hasField('author_heading');

        return [
            'header_row'  => $headerRow,
            'column_map'  => $this->columnMap,
            'has_heading' => $hasHeading,
            'format'      => $hasHeading || $this->hasField('item_number') ? 'Daftar Buku DAPD / INLIS' : 'Template ATLAS',
            'last_column' => Coordinate::stringFromColumnIndex(max(array_map(
                fn ($column) => Coordinate::columnIndexFromString($column),
                array_keys($this->columnMap)
            ))),
        ];
    }

    /**
     * Ambil sampel baris awal dari worksheet yang sudah dimuat (untuk CSV/XLS).
     */
    private function sampleWorksheet(Worksheet $worksheet, int $maxRows): array
    {
        $values = [];
        $formulas = [];
        $lastRow = min($maxRows, $worksheet->getHighestDataRow());
        if ($lastRow < 1) {
            return ['values' => [], 'formulas' => []];
        }

        $range = 'A1:' . $worksheet->getHighestDataColumn() . $lastRow;
        foreach ($worksheet->rangeToArray($range, null, false, false, true) as $rowIndex => $cells) {
            foreach ($cells as $column => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if (is_string($value) && str_starts_with($value, '=')) {
                    $formulas[$rowIndex][$column] = true;
                    $value = $worksheet->getCell($column . $rowIndex)->getOldCalculatedValue();
                }
                $values[$rowIndex][$column] = (string) $value;
            }
        }

        return ['values' => $values, 'formulas' => $formulas];
    }

    /**
     * Baca baris awal setiap sheet XLSX langsung dari XML di dalam arsip (streaming),
     * tanpa memuat seluruh workbook. Mengembalikan [nama sheet => [values, formulas]].
     */
    private function sniffXlsxSheets(string $filePath, int $maxRows): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $hasSharedStrings = $zip->locateName('xl/sharedStrings.xml') !== false;
        $zip->close();

        if (! $workbook || ! $rels) {
            return [];
        }

        $targets = [];
        foreach ($rels->Relationship as $relationship) {
            $target = ltrim((string) $relationship['Target'], '/');
            $targets[(string) $relationship['Id']] = str_starts_with($target, 'xl/') ? $target : "xl/{$target}";
        }

        $sheets = [];
        $neededStrings = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $relationId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $path = $targets[$relationId] ?? null;
            if (! $path) {
                continue;
            }

            $sample = ['values' => [], 'formulas' => [], 'shared' => []];
            $xml = new XMLReader();
            if (! $xml->open("zip://{$filePath}#{$path}")) {
                continue;
            }

            $rowIndex = 0;
            while ($xml->read()) {
                if ($xml->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }
                if ($xml->name === 'row') {
                    $rowIndex = (int) $xml->getAttribute('r');
                    if ($rowIndex > $maxRows) {
                        break;
                    }
                    continue;
                }
                if ($xml->name !== 'c') {
                    continue;
                }

                $column = preg_replace('/\d+/', '', (string) $xml->getAttribute('r'));
                $type = $xml->getAttribute('t');
                $cell = new SimpleXMLElement($xml->readOuterXml());
                $value = $type === 'inlineStr' ? (string) $cell->is->t : (string) $cell->v;
                if ($value === '') {
                    continue;
                }

                if (isset($cell->f)) {
                    $sample['formulas'][$rowIndex][$column] = true;
                }
                if ($type === 's') {
                    $sample['shared'][$rowIndex][$column] = (int) $value;
                    $neededStrings[(int) $value] = true;
                } else {
                    $sample['values'][$rowIndex][$column] = $value;
                }
            }
            $xml->close();

            $sheets[(string) $sheet['name']] = $sample;
        }

        // Ambil hanya shared string yang dipakai di baris sampel
        $strings = $hasSharedStrings && $neededStrings
            ? $this->readSharedStrings($filePath, $neededStrings)
            : [];

        foreach ($sheets as &$sample) {
            foreach ($sample['shared'] as $rowIndex => $cells) {
                foreach ($cells as $column => $index) {
                    $sample['values'][$rowIndex][$column] = $strings[$index] ?? '';
                }
            }
            unset($sample['shared']);
            ksort($sample['values']);
        }
        unset($sample);

        return $sheets;
    }

    private function readSharedStrings(string $filePath, array $neededIndexes): array
    {
        $maxIndex = max(array_keys($neededIndexes));
        $strings = [];
        $xml = new XMLReader();
        if (! $xml->open("zip://{$filePath}#xl/sharedStrings.xml")) {
            return [];
        }

        $index = -1;
        while ($xml->read()) {
            if ($xml->nodeType !== XMLReader::ELEMENT || $xml->name !== 'si') {
                continue;
            }
            $index++;
            if ($index > $maxIndex) {
                break;
            }
            if (isset($neededIndexes[$index])) {
                // Teks biasa (<t>) maupun rich text (<r><t>) digabung
                $node = new SimpleXMLElement($xml->readOuterXml());
                $text = '';
                foreach ($node->xpath('.//*[local-name()="t"]') as $part) {
                    $text .= (string) $part;
                }
                $strings[$index] = $text;
            }
        }
        $xml->close();

        return $strings;
    }

    /**
     * Tahap 2: simpan sekumpulan baris hasil prepare() ke database
     * (commit per blok; baris yang gagal hanya membatalkan baris itu sendiri).
     */
    public function processRows(array $rows): array
    {
        @set_time_limit(0);

        $result = [
            'processed_rows'      => 0,
            'catalogs_created'    => 0,
            'catalogs_matched'    => 0,
            'collections_created' => 0,
            'duplicate_barcodes'  => 0,
            'sheet_collections'   => [],
            'errors'              => [],
            'warnings'            => [],
        ];
        $affectedCatalogIds = [];

        foreach (array_chunk($rows, self::CHUNK_ROWS) as $chunk) {
            DB::beginTransaction();

            try {
                foreach ($chunk as $row) {
                    $result['processed_rows']++;
                    $label = $row['label'];

                    try {
                        $outcome = DB::transaction(fn () => $this->importRow($row['data'], $row['title'], $label));
                    } catch (Throwable $e) {
                        $this->report($result['errors'], "{$label}: gagal disimpan - {$e->getMessage()}");
                        continue;
                    }

                    if ($outcome['skipped_duplicate']) {
                        $result['duplicate_barcodes']++;
                        $this->report($result['warnings'], $outcome['message']);
                        continue;
                    }

                    $result[$outcome['catalog_created'] ? 'catalogs_created' : 'catalogs_matched']++;
                    $result['collections_created'] += $outcome['collections_created'];
                    $result['sheet_collections'][$row['sheet']] = ($result['sheet_collections'][$row['sheet']] ?? 0) + $outcome['collections_created'];
                    $affectedCatalogIds[$outcome['catalog_id']] = true;

                    foreach ($outcome['warnings'] as $warning) {
                        $this->report($result['warnings'], "{$label}: {$warning}");
                    }
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        $this->recalculateCopies(array_keys($affectedCatalogIds));

        return $result;
    }

    /**
     * Simpan satu baris: cari/buat katalog lalu buat eksemplar sejumlah EKS.
     */
    private function importRow(array $data, string $title, string $label): array
    {
        $warnings = [];
        $barcode = $this->text($data, 'barcode_no');

        if ($barcode && Collection::where('barcode_no', $barcode)->exists()) {
            return [
                'skipped_duplicate' => true,
                'message'           => "{$label}: barcode {$barcode} sudah terdaftar, baris dilewati.",
            ];
        }

        $isbn = $this->text($data, 'isbn');
        $publisher = $this->cleanIsbd($this->text($data, 'publisher'));
        $publishYear = $this->year($this->text($data, 'publish_year'));
        $itemNumber = $this->text($data, 'item_number');

        // Baris tanpa barcode: cegah eksemplar ganda saat file yang sama diimpor ulang, memakai
        // NO. INDUK pada judul yang sama (NO. INDUK DAPD tidak selalu unik antar judul).
        if (! $barcode && $itemNumber) {
            $existingCatalog = $this->findCatalog($title, $isbn, $publisher, $publishYear);
            if ($existingCatalog && Collection::where('catalog_id', $existingCatalog->id)->where('item_number', $itemNumber)->exists()) {
                return [
                    'skipped_duplicate' => true,
                    'message'           => "{$label}: NO. INDUK {$itemNumber} sudah tercatat untuk judul ini, baris dilewati.",
                ];
            }
        }

        [$catalog, $catalogCreated] = $this->findOrCreateCatalog($data, $title, $isbn, $publisher, $publishYear);

        // Ketersediaan: tersedia, rusak, atau hilang (status "dipinjam" tidak diimpor karena tidak ada data transaksinya)
        $availabilityText = $this->text($data, 'availability') ?? '';
        $availabilityLower = Str::lower($availabilityText);
        $condition = match (true) {
            str_contains($availabilityLower, 'hilang') => 'Hilang',
            str_contains($availabilityLower, 'rusak') => 'Rusak',
            default => 'Baik',
        };
        if (str_contains($availabilityLower, 'pinjam') && ! str_contains($availabilityLower, 'tersedia')) {
            $warnings[] = "status \"{$availabilityText}\" diimpor sebagai Tersedia karena belum ada data peminjamannya.";
        }

        // "Tersedia di Mobil Putih" -> lokasi tambahan "Mobil Putih"
        $availabilityPlace = preg_match('/\bdi\s+(.+)$/i', $availabilityText, $m) ? trim($m[1]) : null;
        $roomLocation = collect([$this->text($data, 'room_location_code'), $availabilityPlace])->filter()->implode(' • ') ?: null;

        $copies = (int) ($this->number($this->text($data, 'copies')) ?? 1);
        if ($copies > self::MAX_COPIES_PER_ROW) {
            $warnings[] = "EKS {$copies} dibatasi menjadi " . self::MAX_COPIES_PER_ROW . ' eksemplar.';
        }
        $copies = min(max($copies, 1), self::MAX_COPIES_PER_ROW);

        $isQuarantine = $condition !== 'Baik';

        for ($i = 1; $i <= $copies; $i++) {
            // Eksemplar ke-2 dst. memakai akhiran -2, -3, ... agar barcode & no. induk tetap unik
            $copyBarcode = $barcode ? ($i === 1 ? $barcode : "{$barcode}-{$i}") : $this->generateBarcode();
            if ($i > 1 && Collection::where('barcode_no', $copyBarcode)->exists()) {
                $copyBarcode = $this->generateBarcode();
            }

            Collection::create([
                'catalog_id'            => $catalog->id,
                'item_number'           => $itemNumber ? ($i === 1 ? $itemNumber : "{$itemNumber}-{$i}") : null,
                'barcode_no'            => $copyBarcode,
                'rfid_no'               => $i === 1 ? $this->text($data, 'rfid_no') : null,
                'exemplar_call_number'  => $this->text($data, 'exemplar_call_number') ?? $this->text($data, 'call_number', 0) ?? $catalog->catalog_call_number,
                'procurement_date'      => $this->date($data, 'procurement_date') ?? now()->toDateString(),
                'source_type'           => $this->text($data, 'source_type') ?? 'Pembelian',
                'source_name'           => $this->text($data, 'source_name'),
                'currency'              => $this->text($data, 'currency') ?? 'IDR',
                'price'                 => $this->number($this->raw($data, 'price') ?? $this->text($data, 'price')) ?? 0,
                'library_location_code' => $this->text($data, 'library_location_code') ?? 'Pusat',
                'room_location_code'    => $roomLocation,
                'access_type'           => $this->text($data, 'access_type') ?? 'Dapat dipinjam',
                'category'              => $this->text($data, 'category') ?? 'Koleksi Umum',
                'availability_status'   => $isQuarantine ? 'QUARANTINED' : 'AVAILABLE',
                'physical_condition'    => $condition,
                'is_quarantine'         => $isQuarantine,
                'quarantine_reason'     => $isQuarantine ? "Import Excel: {$availabilityText}" : null,
            ]);
        }

        return [
            'skipped_duplicate'   => false,
            'catalog_id'          => $catalog->id,
            'catalog_created'     => $catalogCreated,
            'collections_created' => $copies,
            'warnings'            => $warnings,
        ];
    }

    /**
     * Deduplikasi katalog: ISBN, atau kombinasi Judul + Penerbit + Tahun.
     *
     * @return array{0: Catalog, 1: bool}
     */
    private function findOrCreateCatalog(array $data, string $title, ?string $isbn, ?string $publisher, ?string $publishYear): array
    {
        $catalog = $this->findCatalog($title, $isbn, $publisher, $publishYear);

        if ($catalog) {
            return [$catalog, false];
        }

        // Format DAPD: TAJUK PENGARANG (1) = pengarang utama, (2) = badan korporasi,
        // PENGARANG (1)/(2) = pengarang tambahan. Format lama: "pengarang" = pengarang utama.
        $hasHeading = (bool) ($data['_has_heading'] ?? false);
        $authorMain = $hasHeading ? $this->text($data, 'author_heading', 0) : $this->text($data, 'author', 0);

        $language = $this->text($data, 'language');

        $catalog = Catalog::create([
            'material_type'                => $this->text($data, 'material_type') ?? 'Monograf',
            'media_type'                   => $this->text($data, 'media_type') ?? 'Buku',
            'title'                        => $title,
            'subtitle'                     => $this->cleanIsbd($this->text($data, 'subtitle')),
            'statement_of_responsibility'  => $this->cleanIsbd($this->text($data, 'statement_of_responsibility')),
            'author_main'                  => $authorMain,
            'author_corporate'             => $this->text($data, 'author_corporate') ?? ($hasHeading ? $this->text($data, 'author_heading', 1) : null),
            'author_additional_person'     => $this->text($data, 'author_added_person') ?? ($hasHeading ? $this->text($data, 'author', 0) : null),
            'author_additional_corporate'  => $this->text($data, 'author_added_corporate') ?? ($hasHeading ? $this->text($data, 'author', 1) : null),
            'edition'                      => $this->cleanIsbd($this->text($data, 'edition')),
            'publish_city'                 => $this->cleanIsbd($this->text($data, 'publish_city')),
            'publisher'                    => $publisher,
            'publish_year'                 => $publishYear,
            'pages'                        => $this->text($data, 'pages'),
            'dimension'                    => $this->text($data, 'dimension'),
            'isbn'                         => $isbn,
            'issn'                         => $this->text($data, 'issn'),
            'ismn'                         => $this->text($data, 'ismn'),
            'ddc_number'                   => $this->text($data, 'ddc_number'),
            // Kolom NOMOR PANGGIL kedua = nomor panggil katalog; jika hanya satu, dipakai untuk keduanya
            'catalog_call_number'          => $this->text($data, 'catalog_call_number')
                ?? $this->text($data, 'call_number', 1)
                ?? $this->text($data, 'call_number', 0)
                ?? $this->text($data, 'exemplar_call_number'),
            'subject_topic'                => $this->text($data, 'subject_topic'),
            'language'                     => $language ? (self::LANGUAGE_CODES[Str::lower($language)] ?? $language) : 'Indonesia',
            'abstract'                     => $this->text($data, 'abstract'),
            'serial_edition'               => $this->text($data, 'serial_edition'),
            'serial_publish_date'          => $this->date($data, 'serial_publish_date'),
            'serial_accompanying_material' => $this->text($data, 'serial_accompanying_material'),
            'serial_notes'                 => $this->text($data, 'serial_notes'),
            'is_quarantine'                => false,
        ]);

        return [$catalog, true];
    }

    private function findCatalog(string $title, ?string $isbn, ?string $publisher, ?string $publishYear): ?Catalog
    {
        $catalog = $isbn ? Catalog::where('isbn', $isbn)->first() : null;

        return $catalog ?? Catalog::where('title', $title)
            ->when($publisher, fn ($q) => $q->where('publisher', $publisher))
            ->when($publishYear, fn ($q) => $q->where('publish_year', $publishYear))
            ->first();
    }

    /**
     * Cari baris judul kolom pada baris sampel (harus memuat judul + kolom data buku lain).
     */
    private function detectHeaderRow(array $rows): ?int
    {
        $aliasLookup = [];
        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $aliasLookup[$alias] = $field;
            }
        }

        foreach ($rows as $rowIndex => $row) {
            $map = [];
            $occurrences = [];
            foreach ($row as $col => $value) {
                $slug = Str::slug(trim((string) $value), '_');
                $field = $aliasLookup[$slug] ?? null;
                if (! $field) {
                    continue;
                }
                $occurrences[$field] = ($occurrences[$field] ?? -1) + 1;
                $map[$col] = [$field, $occurrences[$field]];
            }

            if (isset($occurrences['title']) && count($occurrences) >= self::MIN_HEADER_FIELDS) {
                $this->columnMap = $map;

                return $rowIndex;
            }
        }

        return null;
    }

    /**
     * Baca blok baris sekaligus (jauh lebih cepat daripada membaca sel satu per satu).
     * Mengembalikan [nilai teks, nilai mentah (untuk tanggal & angka)],
     * keduanya dikunci nomor baris & huruf kolom; sel/baris kosong tidak disertakan.
     */
    private function readRange(Worksheet $worksheet, int $fromRow, int $toRow, string $lastColumn): array
    {
        if ($toRow < $fromRow) {
            return [[], []];
        }

        $range = "A{$fromRow}:{$lastColumn}{$toRow}";
        $rawRows = $worksheet->rangeToArray($range, null, false, false, true);

        $formatted = [];
        $raw = [];
        foreach ($rawRows as $rowIndex => $cells) {
            foreach ($cells as $column => $value) {
                // Sel rumus: pakai hasil terakhir yang tersimpan (tidak dihitung ulang)
                if (is_string($value) && str_starts_with($value, '=')) {
                    $value = $worksheet->getCell($column . $rowIndex)->getOldCalculatedValue();
                }
                if ($value === null || $value === '' || is_bool($value)) {
                    continue;
                }
                $formatted[$rowIndex][$column] = is_float($value) && floor($value) === $value && abs($value) < 1e15
                    ? (string) (int) $value
                    : (string) $value;
                $raw[$rowIndex][$column] = $value;
            }
        }

        return [$formatted, $raw];
    }

    /**
     * Hitung ulang total & stok tersedia katalog yang terdampak dengan satu query per blok
     * (aturan sama dengan Catalog::recalculateCopies()).
     */
    private function recalculateCopies(array $catalogIds): void
    {
        foreach (array_chunk($catalogIds, 1000) as $ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            DB::update(
                "UPDATE catalogs SET
                    total_copies = (SELECT COUNT(*) FROM collections c WHERE c.catalog_id = catalogs.id),
                    available_copies = (
                        SELECT COUNT(*) FROM collections c
                        WHERE c.catalog_id = catalogs.id
                          AND c.availability_status = 'AVAILABLE'
                          AND c.is_quarantine = 0
                          AND (c.physical_condition IS NULL OR c.physical_condition NOT IN ('Rusak', 'Rusak Ringan', 'Rusak Berat', 'Hilang'))
                    )
                WHERE id IN ({$placeholders})",
                $ids
            );
        }
    }

    private function mapRow(array $formattedRow, array $rawRow): array
    {
        $data = [];
        foreach ($this->columnMap as $col => [$field, $occurrence]) {
            $data[$field][$occurrence] = [
                'text' => $formattedRow[$col] ?? null,
                'raw'  => $rawRow[$col] ?? null,
            ];
        }

        return $data;
    }

    private function hasField(string $field): bool
    {
        foreach ($this->columnMap as [$mapped]) {
            if ($mapped === $field) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nilai teks yang sudah dirapikan; isian kosong seperti "-" atau "---Belum ditentukan---" menjadi null.
     */
    private function text(array $data, string $field, int $occurrence = 0): ?string
    {
        $value = $data[$field][$occurrence]['text'] ?? null;
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        if ($value === '' || preg_match('/^[-–—.\s]+$/u', $value) || Str::contains(Str::lower($value), 'belum ditentukan')) {
            return null;
        }

        return $value;
    }

    private function raw(array $data, string $field, int $occurrence = 0): mixed
    {
        return $data[$field][$occurrence]['raw'] ?? null;
    }

    /**
     * Hapus tanda baca ISBD di akhir teks, mis. "Jakarta :" -> "Jakarta", "Grafika," -> "Grafika".
     */
    private function cleanIsbd(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = trim(preg_replace('/[\s:;\/,=]+$/u', '', $value));

        return $cleaned === '' ? null : $cleaned;
    }

    private function year(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/\d{4}/', $value, $m) ? $m[0] : $value;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        $digits = preg_replace('/[^\d]/', '', (string) $value);

        return $digits === '' ? null : (float) $digits;
    }

    /**
     * Tanggal dari serial Excel atau teks dd/mm/yyyy, dd-mm-yyyy, yyyy-mm-dd.
     */
    private function date(array $data, string $field): ?string
    {
        $raw = $this->raw($data, $field);

        if (is_numeric($raw) && $raw > 0) {
            return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
        }

        $value = $this->text($data, $field);
        if ($value === null) {
            return null;
        }

        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'Y-m-d', 'd/m/y', 'm/d/Y', 'n/j/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // coba format berikutnya
            }
        }

        return null;
    }

    private function generateBarcode(): string
    {
        $prefix = 'IMP-' . now()->format('Ymd') . '-';
        $last = Collection::where('barcode_no', 'like', "{$prefix}%")->orderByDesc('barcode_no')->value('barcode_no');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        do {
            $candidate = $prefix . str_pad((string) $sequence++, 5, '0', STR_PAD_LEFT);
        } while (Collection::where('barcode_no', $candidate)->exists());

        return $candidate;
    }

    private function memoryLimitBytes(): int
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };
    }

    private function report(array &$bucket, string $message): void
    {
        if (count($bucket) < self::MAX_REPORTED_MESSAGES) {
            $bucket[] = $message;
        }
    }
}
