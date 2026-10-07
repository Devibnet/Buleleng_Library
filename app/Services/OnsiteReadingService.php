<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Guestbook;
use App\Models\Member;
use App\Models\OnsiteReading;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class OnsiteReadingService
{
    /**
     * 1. ENTRY BACA DI TEMPAT - ANGGOTA (recordMemberReading)
     */
    public function recordMemberReading(string $memberNo, string $collectionCode): OnsiteReading
    {
        $member = Member::where('member_no', $memberNo)
            ->orWhere('id', $memberNo)
            ->first();

        if (! $member) {
            throw new ModelNotFoundException("Anggota dengan nomor/ID '{$memberNo}' tidak ditemukan.");
        }

        $collection = Collection::where('barcode_no', $collectionCode)->first();

        if (! $collection) {
            throw new ModelNotFoundException("Buku/koleksi dengan barcode '{$collectionCode}' tidak ditemukan.");
        }

        // Cekapakah ada record guestbooks untuk member ini pada tanggal hari ini
        $guestbook = Guestbook::where('member_id', $member->id)
            ->whereDate('visit_date', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $guestbookId = $guestbook ? $guestbook->id : null;

        // Simpan ke onsite_readings (TIDAK MENGURANGI stok available_copies)
        return OnsiteReading::create([
            'member_id'     => $member->id,
            'guestbook_id'  => $guestbookId,
            'collection_id' => $collection->id,
            'read_at'       => now(),
        ]);
    }

    /**
     * 2. ENTRY BACA DI TEMPAT - NON ANGGOTA / GUESTBOOK (recordGuestReading)
     */
    public function recordGuestReading(int $guestbookId, string $collectionCode): OnsiteReading
    {
        $guestbook = Guestbook::find($guestbookId);

        if (! $guestbook) {
            throw new ModelNotFoundException("Record buku tamu dengan ID '{$guestbookId}' tidak ditemukan.");
        }

        $collection = Collection::where('barcode_no', $collectionCode)->first();

        if (! $collection) {
            throw new ModelNotFoundException("Buku/koleksi dengan barcode '{$collectionCode}' tidak ditemukan.");
        }

        return OnsiteReading::create([
            'guestbook_id'  => $guestbook->id,
            'member_id'     => null,
            'collection_id' => $collection->id,
            'read_at'       => now(),
        ]);
    }

    /**
     * 3. REKAP & STATISTIK BACA DI TEMPAT (getReports)
     */
    public function getReports(?string $startDate = null, ?string $endDate = null, int $perPage = 15): array
    {
        $query = OnsiteReading::with(['member', 'guestbook', 'collection.catalog']);

        if (! empty($startDate)) {
            $query->whereDate('read_at', '>=', $startDate);
        }

        if (! empty($endDate)) {
            $query->whereDate('read_at', '<=', $endDate);
        }

        $totalReadings = (clone $query)->count();

        // Agregasi Top Read Collections (Buku paling sering dibaca di tempat)
        $topReadCollections = DB::table('onsite_readings')
            ->join('collections', 'onsite_readings.collection_id', '=', 'collections.id')
            ->join('catalogs', 'collections.catalog_id', '=', 'catalogs.id')
            ->select([
                'catalogs.id as catalog_id',
                'catalogs.title as catalog_title',
                'catalogs.author_main',
                'catalogs.isbn',
                DB::raw('COUNT(onsite_readings.id) as total_reads'),
            ])
            ->when(! empty($startDate), function ($q) use ($startDate) {
                $q->whereDate('onsite_readings.read_at', '>=', $startDate);
            })
            ->when(! empty($endDate), function ($q) use ($endDate) {
                $q->whereDate('onsite_readings.read_at', '<=', $endDate);
            })
            ->groupBy('catalogs.id', 'catalogs.title', 'catalogs.author_main', 'catalogs.isbn')
            ->orderBy(DB::raw('COUNT(onsite_readings.id)'), 'desc')
            ->limit(10)
            ->get();

        $logs = $query->orderBy('id', 'desc')->paginate($perPage);

        return [
            'summary' => [
                'total_readings' => $totalReadings,
            ],
            'top_read_collections' => $topReadCollections,
            'logs'                 => $logs,
        ];
    }
}
