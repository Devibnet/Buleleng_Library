<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Guestbook;
use App\Models\Loan;
use App\Models\Member;
use App\Models\OnsiteReading;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * 1. LAPORAN SIRKULASI & KINERJA OPERATOR
     */
    public function getCirculationReport(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Loan::query()->with(['member', 'collection.catalog', 'createdByAdmin', 'returnedByAdmin']);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $loans = $query->latest()->get();

        // Agregasi dasar
        $totalLoans = $loans->count();
        $totalBorrowed = $loans->whereIn('status', [Loan::STATUS_BORROWED, Loan::STATUS_RETURNED])->count();
        $totalReturned = $loans->where('status', Loan::STATUS_RETURNED)->count();
        $totalOverdue = $loans->filter(function ($loan) {
            return $loan->status === Loan::STATUS_OVERDUE || $loan->late_days > 0;
        })->count();
        $totalFineAmount = (float) $loans->sum('fine_amount');

        // Top 10 Buku Paling Sering Dipinjam
        $topBooks = DB::table('loans')
            ->join('collections', 'loans.collection_id', '=', 'collections.id')
            ->join('catalogs', 'collections.catalog_id', '=', 'catalogs.id')
            ->when($startDate, fn($q) => $q->whereDate('loans.created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('loans.created_at', '<=', $endDate))
            ->select('catalogs.id', 'catalogs.title', 'catalogs.author_main', 'catalogs.isbn', DB::raw('COUNT(loans.id) as total_borrowed'))
            ->groupBy('catalogs.id', 'catalogs.title', 'catalogs.author_main', 'catalogs.isbn')
            ->orderByDesc('total_borrowed')
            ->limit(10)
            ->get();

        // Top 10 Anggota Paling Aktif Meminjam
        $topMembers = DB::table('loans')
            ->join('members', 'loans.member_id', '=', 'members.id')
            ->when($startDate, fn($q) => $q->whereDate('loans.created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('loans.created_at', '<=', $endDate))
            ->select('members.id', 'members.member_no', 'members.name', 'members.job_category', DB::raw('COUNT(loans.id) as total_loans'))
            ->groupBy('members.id', 'members.member_no', 'members.name', 'members.job_category')
            ->orderByDesc('total_loans')
            ->limit(10)
            ->get();

        // Rekapitulasi Denda & Pelanggaran
        $finesSummary = [
            'total_fines_count' => $loans->where('fine_amount', '>', 0)->count(),
            'total_fine_amount' => $totalFineAmount,
            'total_late_days'   => (int) $loans->sum('late_days'),
        ];

        // Laporan Kinerja Operator
        $operatorPerformance = DB::table('admins')
            ->leftJoin('loans as created_loans', function ($join) use ($startDate, $endDate) {
                $join->on('admins.id', '=', 'created_loans.created_by_user_id');
                if ($startDate) $join->whereDate('created_loans.created_at', '>=', $startDate);
                if ($endDate) $join->whereDate('created_loans.created_at', '<=', $endDate);
            })
            ->leftJoin('loans as returned_loans', function ($join) use ($startDate, $endDate) {
                $join->on('admins.id', '=', 'returned_loans.returned_by_user_id');
                if ($startDate) $join->whereDate('returned_loans.created_at', '>=', $startDate);
                if ($endDate) $join->whereDate('returned_loans.created_at', '<=', $endDate);
            })
            ->select(
                'admins.id',
                'admins.username',
                'admins.name',
                'admins.role',
                DB::raw('COUNT(DISTINCT created_loans.id) as processed_borrows'),
                DB::raw('COUNT(DISTINCT returned_loans.id) as processed_returns')
            )
            ->groupBy('admins.id', 'admins.username', 'admins.name', 'admins.role')
            ->get();

        return [
            'period' => [
                'start_date' => $startDate ?? 'Semua Waktu',
                'end_date'   => $endDate ?? 'Semua Waktu',
            ],
            'summary' => [
                'total_loans'     => $totalLoans,
                'total_borrowed'  => $totalBorrowed,
                'total_returned'  => $totalReturned,
                'total_overdue'   => $totalOverdue,
                'total_fine_amount' => $totalFineAmount,
            ],
            'top_books'            => $topBooks,
            'top_members'          => $topMembers,
            'fines_summary'        => $finesSummary,
            'operator_performance' => $operatorPerformance,
            'loans_list'           => $loans,
        ];
    }

    /**
     * 2. LAPORAN PENAMBAHAN ANGGOTA
     */
    public function getMemberReport(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Member::query();

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $members = $query->latest()->get();

        // Breakdown statistik berdasarkan job_category
        $jobCategoryBreakdown = $members->groupBy('job_category')
            ->map(fn($group) => $group->count())
            ->toArray();

        return [
            'period' => [
                'start_date' => $startDate ?? 'Semua Waktu',
                'end_date'   => $endDate ?? 'Semua Waktu',
            ],
            'total_new_members'      => $members->count(),
            'job_category_breakdown' => $jobCategoryBreakdown,
            'members_list'           => $members,
        ];
    }

    /**
     * 3. LAPORAN KATALOG & KOLEKSI
     */
    public function getCatalogReport(?string $startDate = null, ?string $endDate = null): array
    {
        $catalogQuery = Catalog::query();
        $collectionQuery = Collection::query()->with('catalog');

        if ($startDate) {
            $catalogQuery->whereDate('created_at', '>=', $startDate);
            $collectionQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $catalogQuery->whereDate('created_at', '<=', $endDate);
            $collectionQuery->whereDate('created_at', '<=', $endDate);
        }

        $catalogs = $catalogQuery->get();
        $collections = $collectionQuery->get();

        // Kondisi fisik / status ketersediaan koleksi
        $availabilityStatusBreakdown = $collections->groupBy('availability_status')
            ->map(fn($group) => $group->count())
            ->toArray();

        $quarantineCount = $collections->where('is_quarantine', true)->count();

        return [
            'period' => [
                'start_date' => $startDate ?? 'Semua Waktu',
                'end_date'   => $endDate ?? 'Semua Waktu',
            ],
            'summary' => [
                'total_catalog_titles' => $catalogs->count(),
                'total_collections'    => $collections->count(),
                'total_available'      => $collections->where('availability_status', 'AVAILABLE')->where('is_quarantine', false)->count(),
                'total_borrowed'       => $collections->where('availability_status', 'BORROWED')->count(),
                'total_quarantine'     => $quarantineCount,
            ],
            'availability_status_breakdown' => $availabilityStatusBreakdown,
            'collections_list'              => $collections,
        ];
    }

    /**
     * 4. LAPORAN BACA DI TEMPAT
     */
    public function getOnsiteReadingReport(?string $startDate = null, ?string $endDate = null): array
    {
        $query = OnsiteReading::query()->with(['member', 'guestbook', 'collection.catalog']);

        if ($startDate) {
            $query->whereDate('read_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('read_at', '<=', $endDate);
        }

        $readings = $query->latest('read_at')->get();

        $totalReadings = $readings->count();
        $memberCount = $readings->whereNotNull('member_id')->count();
        $guestCount = $readings->whereNull('member_id')->count();

        // Top Buku yang Paling Sering Dibaca di Tempat
        $topReadCollections = DB::table('onsite_readings')
            ->join('collections', 'onsite_readings.collection_id', '=', 'collections.id')
            ->join('catalogs', 'collections.catalog_id', '=', 'catalogs.id')
            ->when($startDate, fn($q) => $q->whereDate('onsite_readings.read_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('onsite_readings.read_at', '<=', $endDate))
            ->select('catalogs.id', 'catalogs.title', 'catalogs.author_main', 'catalogs.isbn', DB::raw('COUNT(onsite_readings.id) as total_read'))
            ->groupBy('catalogs.id', 'catalogs.title', 'catalogs.author_main', 'catalogs.isbn')
            ->orderByDesc('total_read')
            ->limit(10)
            ->get();

        return [
            'period' => [
                'start_date' => $startDate ?? 'Semua Waktu',
                'end_date'   => $endDate ?? 'Semua Waktu',
            ],
            'summary' => [
                'total_readings' => $totalReadings,
                'member_count'   => $memberCount,
                'guest_count'    => $guestCount,
                'member_ratio'   => $totalReadings > 0 ? round(($memberCount / $totalReadings) * 100, 2) . '%' : '0%',
            ],
            'top_read_collections' => $topReadCollections,
            'readings_list'        => $readings,
        ];
    }

    /**
     * 5. LAPORAN BUKU TAMU / KUNJUNGAN
     */
    public function getGuestbookReport(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Guestbook::query()->with('member');

        if ($startDate) {
            $query->whereDate('visit_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('visit_date', '<=', $endDate);
        }

        $guestbooks = $query->latest('visit_date')->get();

        $totalEntries = $guestbooks->count();
        $totalVisitors = (int) $guestbooks->sum('total_personnel');
        $totalMale = (int) $guestbooks->sum('gender_male_count');
        $totalFemale = (int) $guestbooks->sum('gender_female_count');

        // Breakdown berdasarkan visit_type
        $visitTypeBreakdown = $guestbooks->groupBy('visit_type')
            ->map(fn($group) => [
                'entries_count'  => $group->count(),
                'visitors_count' => (int) $group->sum('total_personnel'),
            ])
            ->toArray();

        // Agregasi JSON job_breakdown dan education_breakdown
        $jobBreakdownSum = [];
        $educationBreakdownSum = [];

        foreach ($guestbooks as $gb) {
            if (is_array($gb->job_breakdown)) {
                foreach ($gb->job_breakdown as $key => $val) {
                    $normKey = strtoupper(trim((string) $key));
                    $jobBreakdownSum[$normKey] = ($jobBreakdownSum[$normKey] ?? 0) + (int) $val;
                }
            }

            if (is_array($gb->education_breakdown)) {
                foreach ($gb->education_breakdown as $key => $val) {
                    $normKey = strtoupper(trim((string) $key));
                    $educationBreakdownSum[$normKey] = ($educationBreakdownSum[$normKey] ?? 0) + (int) $val;
                }
            }
        }

        return [
            'period' => [
                'start_date' => $startDate ?? 'Semua Waktu',
                'end_date'   => $endDate ?? 'Semua Waktu',
            ],
            'summary' => [
                'total_entries'  => $totalEntries,
                'total_visitors' => $totalVisitors,
                'total_male'     => $totalMale,
                'total_female'   => $totalFemale,
            ],
            'visit_type_breakdown'    => $visitTypeBreakdown,
            'job_breakdown_sum'       => $jobBreakdownSum,
            'education_breakdown_sum' => $educationBreakdownSum,
            'guestbooks_list'         => $guestbooks,
        ];
    }
}
