<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Loan;

echo "=== CATALOGS ===" . PHP_EOL;
foreach (Catalog::with('collections')->get() as $cat) {
    echo "ID: {$cat->id} | {$cat->title} | Total: {$cat->total_copies} | Avail: {$cat->available_copies} | Quar: " . ($cat->is_quarantine ? 'YES' : 'NO') . PHP_EOL;
    foreach ($cat->collections as $col) {
        echo "   -> Col ID: {$col->id} | Barcode: {$col->barcode_no} | Status: {$col->availability_status} | Cond: {$col->physical_condition} | Quar: " . ($col->is_quarantine ? 'YES' : 'NO') . PHP_EOL;
    }
}
