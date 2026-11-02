<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;

$catalog = Catalog::firstOrCreate(
    ['id' => 1],
    [
        'title'                       => 'Laskar Pelangi',
        'subtitle'                    => 'Kisah Sepuluh Laskar Pelangi Menggapai Cita-cita',
        'author_main'                 => 'Andrea Hirata',
        'edition'                     => 'Cetakan ke-24',
        'publish_city'                => 'Yogyakarta',
        'publisher'                   => 'Bentang Pustaka',
        'publish_year'                => '2005',
        'pages'                       => '529 hlm.',
        'dimension'                   => '20.5 cm',
        'isbn'                        => '978-979-3062-79-2',
        'ddc_number'                  => '813',
        'catalog_call_number'         => '813 HIR l',
        'subject_topic'               => 'Sastra Indonesia - Novel Fiksi Inspiratif',
        'material_type'               => 'Monograf Cetak',
        'is_quarantine'               => false,
    ]
);
$catalog->update(['is_quarantine' => false]);

$member = Member::firstOrCreate(
    ['member_no' => 'MEM-202610-0001'],
    [
        'name'     => 'I Gede Ryan Mahardika',
        'email'    => 'ryan.mahardika@example.com',
        'phone'    => '081234567890',
        'password' => bcrypt('password123'),
        'status'   => 'ACTIVE',
    ]
);

// 1. Eksemplar 1: Kondisi Baik, Status Tersedia (AVAILABLE)
$col1 = Collection::updateOrCreate(
    ['barcode_no' => 'BUK-00101'],
    [
        'catalog_id'            => $catalog->id,
        'item_number'           => 'ATL-2005/0001',
        'rfid_no'               => 'RF-LP-001',
        'exemplar_call_number'  => '813 HIR l c.1',
        'library_location_code' => 'PUSAT',
        'room_location_code'    => 'Lt. 1 Ruang Baca Fiksi',
        'category'              => 'Sirkulasi Umum',
        'availability_status'   => 'AVAILABLE',
        'physical_condition'    => 'Baik',
        'is_quarantine'         => false,
        'quarantine_reason'     => null,
        'quarantine_notes'      => null,
    ]
);

// 2. Eksemplar 2: Kondisi Rusak, Masuk Karantina (QUARANTINED)
$col2 = Collection::updateOrCreate(
    ['barcode_no' => 'BUK-00102'],
    [
        'catalog_id'            => $catalog->id,
        'item_number'           => 'ATL-2005/0002',
        'rfid_no'               => 'RF-LP-002',
        'exemplar_call_number'  => '813 HIR l c.2',
        'library_location_code' => 'PUSAT',
        'room_location_code'    => 'Laboratorium Konservasi / Karantina',
        'category'              => 'Sirkulasi Umum',
        'availability_status'   => 'QUARANTINED',
        'physical_condition'    => 'Rusak',
        'is_quarantine'         => true,
        'quarantine_reason'     => 'Kondisi rusak: jilidan punggung lepas & lembar halaman terlepas',
        'quarantine_notes'      => 'Disimpan di karantina perbaikan fisik koleksi buku',
    ]
);

// 3. Eksemplar 3: Kondisi Baik, Status Sedang Dipinjam (BORROWED)
$col3 = Collection::updateOrCreate(
    ['barcode_no' => 'BUK-00103'],
    [
        'catalog_id'            => $catalog->id,
        'item_number'           => 'ATL-2005/0003',
        'rfid_no'               => 'RF-LP-003',
        'exemplar_call_number'  => '813 HIR l c.3',
        'library_location_code' => 'PUSAT',
        'room_location_code'    => 'Lt. 1 Ruang Baca Fiksi',
        'category'              => 'Sirkulasi Umum',
        'availability_status'   => 'BORROWED',
        'physical_condition'    => 'Baik',
        'is_quarantine'         => false,
        'quarantine_reason'     => null,
        'quarantine_notes'      => null,
    ]
);

// Buat record loan peminjaman aktif untuk Eksemplar 3
Loan::updateOrCreate(
    [
        'collection_id' => $col3->id,
        'status'        => Loan::STATUS_BORROWED,
    ],
    [
        'loan_number'   => 'LON-202610-0099',
        'member_id'     => $member->id,
        'borrow_date'   => now()->subDays(2),
        'loan_date'     => now()->subDays(2)->toDateString(),
        'due_date'      => now()->addDays(5)->toDateString(),
    ]
);

// Recalculate catalog
$catalog->refresh();
$catalog->recalculateCopies();

echo "Database successfully populated with Laskar Pelangi example scenario!" . PHP_EOL;
echo "Catalog: {$catalog->title}" . PHP_EOL;
echo "  - Total Copies: {$catalog->total_copies}" . PHP_EOL;
echo "  - Available Copies: {$catalog->available_copies}" . PHP_EOL;
echo "  - Borrowed Copies: {$catalog->borrowed_copies_count}" . PHP_EOL;
echo "  - Quarantined Copies: {$catalog->quarantined_copies_count}" . PHP_EOL;
