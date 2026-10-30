<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
use App\Services\CirculationService;
use App\Http\Controllers\Api\V1\Admin\QuarantineController;
use Illuminate\Http\Request;

echo "==========================================================" . PHP_EOL;
echo "  PENGUJIAN LOGIKA INVENTARIS EKSEMPLAR BUKU PERPUSTAKAAN  " . PHP_EOL;
echo "==========================================================" . PHP_EOL . PHP_EOL;

function assertCondition($condition, $message) {
    if (!$condition) {
        echo "❌ GAGAL: {$message}" . PHP_EOL;
        exit(1);
    } else {
        echo "✅ LULUS: {$message}" . PHP_EOL;
    }
}

// Persiapan data
$catalog = Catalog::firstOrCreate(
    ['id' => 1],
    [
        'title' => 'Laskar Pelangi',
        'author_main' => 'Andrea Hirata',
        'publisher' => 'Bentang Pustaka',
        'publish_year' => '2005',
        'isbn' => '978-979-3062-79-2',
        'is_quarantine' => false,
    ]
);
$catalog->update(['is_quarantine' => false]);

// Pastikan ada 3 eksemplar
$barcodes = ['BUK-00101', 'BUK-00102', 'BUK-00103'];
foreach ($barcodes as $i => $bc) {
    Collection::updateOrCreate(
        ['barcode_no' => $bc],
        [
            'catalog_id'          => $catalog->id,
            'item_number'         => 'ATL-2005/' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
            'availability_status' => 'AVAILABLE',
            'physical_condition'  => 'Baik',
            'is_quarantine'       => false,
            'quarantine_reason'   => null,
            'quarantine_notes'    => null,
            'library_location_code' => 'PUSAT',
            'room_location_code'    => 'Lt. 1 Ruang Baca',
        ]
    );
}

// Pastikan ada member untuk testing peminjaman
$member = Member::first();
if (!$member) {
    $member = Member::create([
        'member_no' => 'MEM-TEST-001',
        'name'      => 'Pemustaka Penguji',
        'email'     => 'penguji@example.com',
        'phone'     => '081234567890',
        'password'  => bcrypt('password123'),
        'status'    => 'ACTIVE',
    ]);
}

// Bersihkan loan testing lama
Loan::where('member_id', $member->id)->delete();

$catalog->refresh();
$catalog->recalculateCopies();

echo "--- TAHAP 0: Kondisi Awal ---" . PHP_EOL;
assertCondition($catalog->total_copies === 3, "Total eksemplar harus 3 (Aktual: {$catalog->total_copies})");
assertCondition($catalog->available_copies === 3, "Stok tersedia harus 3 (Aktual: {$catalog->available_copies})");
assertCondition(!$catalog->is_quarantine, "Katalog induk harus aktif di OPAC (is_quarantine = false)");

echo PHP_EOL . "--- TAHAP 1: Eksemplar 2 dimasukkan ke Karantina (Rusak) ---" . PHP_EOL;
$col2 = Collection::where('barcode_no', 'BUK-00102')->first();
$quarantineController = app(QuarantineController::class);

$reqQuarantine = Request::create('/api/v1/admin/quarantine/move', 'POST', [
    'collection_ids'     => [$col2->id],
    'physical_condition' => 'Rusak',
    'reason'             => 'Halaman sobek dan jilidan lepas',
    'notes'              => 'Perlu perbaikan jilid di lab konservasi',
]);
$resQuarantine = $quarantineController->moveToQuarantine($reqQuarantine);
assertCondition($resQuarantine->getStatusCode() === 200, "API moveToQuarantine berhasil merespon 200");

$col1 = Collection::where('barcode_no', 'BUK-00101')->first();
$col2->refresh();
$col3 = Collection::where('barcode_no', 'BUK-00103')->first();
$catalog->refresh();

assertCondition($col2->is_quarantine === true, "Eksemplar 2 harus berstatus karantina (is_quarantine = true)");
assertCondition($col2->availability_status === 'QUARANTINED', "Eksemplar 2 availability_status = QUARANTINED");
assertCondition($col2->physical_condition === 'Rusak', "Eksemplar 2 kondisi fisik = Rusak");
assertCondition($col1->is_quarantine === false && $col1->availability_status === 'AVAILABLE', "Eksemplar 1 TETAP tersedia & tidak terpengaruh");
assertCondition($col3->is_quarantine === false && $col3->availability_status === 'AVAILABLE', "Eksemplar 3 TETAP tersedia & tidak terpengaruh");
assertCondition($catalog->is_quarantine === false, "Katalog induk TETAP aktif di OPAC (tidak ikut karantina)");
assertCondition($catalog->available_copies === 2, "Stok tersedia berkurang menjadi 2 (Aktual: {$catalog->available_copies})");

echo PHP_EOL . "--- TAHAP 2: Eksemplar 3 dipinjam oleh Pemustaka ---" . PHP_EOL;
$circulationService = app(CirculationService::class);
$loan = $circulationService->processDirectBorrow($member->member_no, 'BUK-00103', null, 7);

$col1->refresh();
$col2->refresh();
$col3->refresh();
$catalog->refresh();

assertCondition($col3->availability_status === 'BORROWED', "Eksemplar 3 berstatus BORROWED");
assertCondition($col1->availability_status === 'AVAILABLE', "Eksemplar 1 TETAP berstatus AVAILABLE");
assertCondition($col2->availability_status === 'QUARANTINED', "Eksemplar 2 TETAP berstatus QUARANTINED");

echo PHP_EOL . "--- VERIFIKASI KASUS CONTOH USER ---" . PHP_EOL;
echo "Koleksi: Laskar Pelangi (3 Eksemplar)" . PHP_EOL;
echo "  - Eksemplar 1 (BUK-00101): Status = {$col1->availability_status}, Kondisi = {$col1->physical_condition}" . PHP_EOL;
echo "  - Eksemplar 2 (BUK-00102): Status = {$col2->availability_status}, Kondisi = {$col2->physical_condition}" . PHP_EOL;
echo "  - Eksemplar 3 (BUK-00103): Status = {$col3->availability_status}, Kondisi = {$col3->physical_condition}" . PHP_EOL;
echo "Rekapitulasi Katalog:" . PHP_EOL;
echo "  - Total Eksemplar: {$catalog->total_copies}" . PHP_EOL;
echo "  - Buku Tersedia: {$catalog->available_copies}" . PHP_EOL;
echo "  - Buku Dipinjam: {$catalog->borrowed_copies_count}" . PHP_EOL;
echo "  - Buku Karantina: {$catalog->quarantined_copies_count}" . PHP_EOL;

assertCondition($catalog->total_copies === 3, "Total eksemplar = 3");
assertCondition($catalog->available_copies === 1, "Buku tersedia = 1");
assertCondition($catalog->borrowed_copies_count === 1, "Buku sedang dipinjam = 1");
assertCondition($catalog->quarantined_copies_count === 1, "Buku dalam karantina = 1");

echo PHP_EOL . "--- TAHAP 3: Pengembalian Buku dengan Kondisi Rusak ---" . PHP_EOL;
// Pemustaka mengembalikan Eksemplar 3 dalam kondisi rusak
$returnedLoan = $circulationService->processReturn(
    $member->member_no,
    'BUK-00103',
    null,
    'Rusak Berat',
    'Cover terlepas dan basah saat dipinjam',
    'Perlu pengeringan dan jilid ulang'
);

$col1->refresh();
$col2->refresh();
$col3->refresh();
$catalog->refresh();

assertCondition($col3->availability_status === 'QUARANTINED', "Eksemplar 3 otomatis masuk QUARANTINED karena rusak");
assertCondition($col3->physical_condition === 'Rusak Berat', "Eksemplar 3 kondisi fisik tercatat 'Rusak Berat'");
assertCondition($col3->is_quarantine === true, "Eksemplar 3 is_quarantine = true");
assertCondition($col1->availability_status === 'AVAILABLE', "Eksemplar 1 TETAP AVAILABLE");
assertCondition($catalog->available_copies === 1, "Stok tersedia tetap 1 (Eksemplar 1)");
assertCondition($catalog->borrowed_copies_count === 0, "Buku dipinjam menjadi 0");
assertCondition($catalog->quarantined_copies_count === 2, "Buku karantina sekarang 2 (Eksemplar 2 & 3)");

echo PHP_EOL . "--- TAHAP 4: Eksemplar 2 Selesai Diperbaiki & Dipulihkan ke Tersedia ---" . PHP_EOL;
$reqRestore = Request::create("/api/v1/admin/quarantine/restore/{$col2->id}", 'POST');
$resRestore = $quarantineController->restore($reqRestore, $col2->id);

$col1->refresh();
$col2->refresh();
$col3->refresh();
$catalog->refresh();

assertCondition($resRestore->getStatusCode() === 200, "API restore merespon 200");
assertCondition($col2->availability_status === 'AVAILABLE', "Eksemplar 2 pulih ke AVAILABLE");
assertCondition($col2->physical_condition === 'Baik', "Eksemplar 2 kondisi fisik kembali 'Baik'");
assertCondition($col2->is_quarantine === false, "Eksemplar 2 is_quarantine = false");
assertCondition($col3->availability_status === 'QUARANTINED', "Eksemplar 3 TETAP QUARANTINED (tidak ikut berubah)");
assertCondition($catalog->available_copies === 2, "Stok tersedia meningkat menjadi 2 (Eksemplar 1 & 2)");
assertCondition($catalog->quarantined_copies_count === 1, "Buku karantina sisa 1 (Eksemplar 3)");

echo PHP_EOL . "--- TAHAP 5: Pulihkan Eksemplar 3 kembali ke Kondisi Baik ---" . PHP_EOL;
$resRestore3 = $quarantineController->restore($reqRestore, $col3->id);

$col1->refresh();
$col2->refresh();
$col3->refresh();
$catalog->refresh();

assertCondition($catalog->available_copies === 3, "Seluruh 3 eksemplar kembali tersedia (available_copies = 3)");
assertCondition($catalog->quarantined_copies_count === 0, "Buku karantina = 0");

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo "  SEMUA SKENARIO PENGUJIAN BERHASIL DILEWATI 100% SUKSES! " . PHP_EOL;
echo "==========================================================" . PHP_EOL;
