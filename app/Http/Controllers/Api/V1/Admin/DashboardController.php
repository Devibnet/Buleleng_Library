<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * DDC Class Definitions
     */
    protected array $ddcClasses = [
        '000' => ['name' => 'Karya Umum, Komputer & Informasi', 'color' => '#A855F7'],
        '100' => ['name' => 'Filsafat & Psikologi', 'color' => '#EC4899'],
        '200' => ['name' => 'Agama & Teologi', 'color' => '#3B82F6'],
        '300' => ['name' => 'Ilmu Sosial, Pendidikan & Hukum', 'color' => '#14B8A6'],
        '400' => ['name' => 'Bahasa & Linguistik', 'color' => '#06B6D4'],
        '500' => ['name' => 'Sains Murni & Matematika', 'color' => '#F59E0B'],
        '600' => ['name' => 'Teknologi & Terapan', 'color' => '#EAB308'],
        '700' => ['name' => 'Kesenian, Hiburan & Rekreasi', 'color' => '#84CC16'],
        '800' => ['name' => 'Sastra, Puisi & Bahasa', 'color' => '#6366F1'],
        '900' => ['name' => 'Sejarah, Biografi & Geografi', 'color' => '#F97316'],
    ];

    /**
     * Get aggregated Dashboard data from the database.
     */
    public function index(): JsonResponse
    {
        // 1. Core Summary Statistics
        $totalCatalogs = Catalog::count();
        $thisMonthCatalogs = Catalog::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $isbnVerified = Catalog::whereNotNull('isbn')->where('isbn', '!=', '')->count();

        $totalCollections = Collection::count();
        $availableCollections = Collection::where('availability_status', 'AVAILABLE')
            ->where('is_quarantine', false)
            ->count();
        $borrowedCollections = Collection::where('availability_status', 'BORROWED')->count();

        $quarantineCatalogs = Catalog::where('is_quarantine', true)->count();
        $quarantineCollections = Collection::where('is_quarantine', true)->count();
        $totalQuarantine = $quarantineCatalogs + $quarantineCollections;

        $missingDdc = Catalog::where(function ($q) {
            $q->whereNull('ddc_number')->orWhere('ddc_number', '');
        })->count();

        $digitalCount = Catalog::where(function ($q) {
            $q->where('material_type', 'like', '%digital%')
              ->orWhere('material_type', 'like', '%elektronik%')
              ->orWhere('material_type', 'like', '%e-book%');
        })->count();

        $totalMembers = Member::count();
        $todayCirculation = Loan::whereDate('created_at', Carbon::today())->count();

        // 2. DDC Distribution Calculations
        $allCatalogs = Catalog::select('id', 'ddc_number', 'material_type')->get();
        $ddcCounts = array_fill_keys(array_keys($this->ddcClasses), 0);
        $unclassifiedCount = 0;

        foreach ($allCatalogs as $cat) {
            $ddc = trim((string)$cat->ddc_number);
            // Bersihkan simbol minus atau tanda kurung bila ada
            $cleaned = ltrim($ddc, '-([ ');
            if (strlen($cleaned) >= 1 && ctype_digit($cleaned[0])) {
                $hundredClass = $cleaned[0] . '00';
                if (isset($ddcCounts[$hundredClass])) {
                    $ddcCounts[$hundredClass]++;
                } else {
                    $unclassifiedCount++;
                }
            } else {
                $unclassifiedCount++;
            }
        }

        $ddcItems = [];
        foreach ($this->ddcClasses as $code => $meta) {
            $count = $ddcCounts[$code];
            $pct = $totalCatalogs > 0 ? round(($count / $totalCatalogs) * 100) : 0;
            $ddcItems[] = [
                'code'        => $code,
                'name'        => "{$code} - {$meta['name']}",
                'count'       => $count,
                'percentage'  => $pct,
                'color'       => $meta['color'],
            ];
        }

        // Urutkan DDC terbesar untuk bar display
        $sortedDdc = $ddcItems;
        usort($sortedDdc, fn($a, $b) => $b['count'] <=> $a['count']);
        $topDdc = array_slice($sortedDdc, 0, 5);

        // 3. Recent Processing & Cataloging Activities
        $recentCatalogs = Catalog::with(['collections'])
            ->latest('id')
            ->take(6)
            ->get()
            ->map(function ($cat) {
                $status = 'Draft Siap';
                $statusBadge = 'bg-[#DCFCE7] text-[#15803D] border border-[#BBF7D0]';
                $actionText = 'Edit';
                $actionColor = 'text-blue-600 hover:text-blue-800';

                if ($cat->is_quarantine) {
                    $status = 'Karantina';
                    $statusBadge = 'bg-[#FEF3C7] text-[#B45309] border border-[#FDE68A]';
                    $actionText = 'Tinjau';
                    $actionColor = 'text-amber-600 hover:text-amber-800';
                } elseif (($cat->available_copies ?? 0) > 0 || $cat->collections->count() > 0) {
                    $status = 'OPAC';
                    $statusBadge = 'bg-[#DBEAFE] text-[#1D4ED8] border border-[#BFDBFE]';
                }

                $ddcClean = trim((string)$cat->ddc_number);
                if (empty($ddcClean)) {
                    $ddcClean = 'N/A';
                }

                $subtitleParts = array_filter([
                    $cat->author_main ?? 'Pustakawan DAPD',
                    $cat->publisher ?? 'DAPD Buleleng',
                ]);

                return [
                    'id'          => $cat->id,
                    'title'       => $cat->title,
                    'subtitle'    => implode(' • ', $subtitleParts),
                    'ddc'         => $ddcClean,
                    'ddcColor'    => $ddcClean === 'N/A' ? 'text-amber-500' : 'text-blue-600',
                    'officer'     => 'Staf Pengolah DAPD',
                    'time'        => $cat->created_at ? $cat->created_at->format('d/m H:i') : now()->format('d/m H:i'),
                    'status'      => $status,
                    'statusBadge' => $statusBadge,
                    'actionText'  => $actionText,
                    'actionColor' => $actionColor,
                ];
            });

        // 4. Physical Condition Status
        $goodConditionCount = Collection::where('availability_status', 'AVAILABLE')
            ->where('is_quarantine', false)
            ->count();
        $damagedCount = Collection::where(function ($q) {
            $q->where('availability_status', 'DAMAGED')
              ->orWhere('availability_status', 'RUSAK');
        })->count();
        $bindingCount = Collection::where(function ($q) {
            $q->where('availability_status', 'BINDING')
              ->orWhere('availability_status', 'REPAIR');
        })->count();
        $lostCount = Collection::where(function ($q) {
            $q->where('availability_status', 'LOST')
              ->orWhere('availability_status', 'HILANG');
        })->count();

        // Hitung persentase fisik
        $calcTotalCol = max($totalCollections, 1);
        $goodPct = round(($goodConditionCount / $calcTotalCol) * 100);
        $borrowedPct = round(($borrowedCollections / $calcTotalCol) * 100);
        $damagedPct = round(($damagedCount / $calcTotalCol) * 100);
        $otherPct = max(0, 100 - $goodPct - $borrowedPct - $damagedPct);

        $physicalStatus = [
            'good' => [
                'label'      => 'Kondisi Baik & Siap Rak',
                'count'      => $goodConditionCount,
                'percentage' => $goodPct,
            ],
            'borrowed' => [
                'label'      => 'Sedang Dipinjam Pemustaka',
                'count'      => $borrowedCollections,
                'percentage' => $borrowedPct,
            ],
            'damaged' => [
                'label'      => 'Rusak / Perlu Perbaikan',
                'count'      => $damagedCount + $bindingCount,
                'percentage' => $damagedPct,
            ],
            'quarantine_or_lost' => [
                'label'      => 'Karantina / Hilang',
                'count'      => $quarantineCollections + $lostCount,
                'percentage' => $otherPct,
            ],
            'last_audit' => Collection::latest('updated_at')->value('updated_at')
                ? Carbon::parse(Collection::latest('updated_at')->value('updated_at'))->isoFormat('D MMMM Y')
                : now()->isoFormat('D MMMM Y'),
        ];

        // 5. Top Circulation Books
        $topLoansQuery = DB::table('loans')
            ->join('collections', 'loans.collection_id', '=', 'collections.id')
            ->join('catalogs', 'collections.catalog_id', '=', 'catalogs.id')
            ->select('catalogs.id', 'catalogs.title', 'catalogs.catalog_call_number', 'catalogs.ddc_number', DB::raw('COUNT(loans.id) as loan_count'))
            ->groupBy('catalogs.id', 'catalogs.title', 'catalogs.catalog_call_number', 'catalogs.ddc_number')
            ->orderByDesc('loan_count')
            ->limit(5)
            ->get();

        $topBooks = [];
        $rank = 1;
        if ($topLoansQuery->isNotEmpty()) {
            foreach ($topLoansQuery as $t) {
                $callNo = $t->catalog_call_number ?: ($t->ddc_number ? $t->ddc_number . ' BLL' : 'BLL-' . str_pad($t->id, 4, '0', STR_PAD_LEFT));
                $topBooks[] = [
                    'rank'       => $rank,
                    'title'      => $t->title,
                    'callNo'     => $callNo,
                    'loans'      => "{$t->loan_count}x Pinjam",
                    'badgeClass' => $rank === 1 ? 'bg-[#2563EB] text-white' : 'bg-[#DBEAFE] text-[#1D4ED8]',
                ];
                $rank++;
            }
        }

        // Jika data peminjaman belum banyak, lengkapi dengan katalog terpopuler/eksemplar terbanyak
        if (count($topBooks) < 3) {
            $fallbackCatalogs = Catalog::orderByDesc('total_copies')->take(3)->get();
            $rank = 1;
            $topBooks = [];
            foreach ($fallbackCatalogs as $fc) {
                $callNo = $fc->catalog_call_number ?: ($fc->ddc_number ? $fc->ddc_number . ' BLL' : 'BLL-' . str_pad($fc->id, 4, '0', STR_PAD_LEFT));
                $copies = max($fc->total_copies ?? 1, 1);
                $topBooks[] = [
                    'rank'       => $rank,
                    'title'      => $fc->title,
                    'callNo'     => $callNo,
                    'loans'      => "{$copies} Eksemplar",
                    'badgeClass' => $rank === 1 ? 'bg-[#2563EB] text-white' : 'bg-[#DBEAFE] text-[#1D4ED8]',
                ];
                $rank++;
            }
        }

        // 6. Problem Members & Circulation Cases Stats (Keranjang Anggota Bermasalah)
        $totalProblemCases = \App\Models\MemberCart::count();
        $activeProblemCases = \App\Models\MemberCart::where('is_resolved', false)->count();
        $uniqueProblemMembers = \App\Models\MemberCart::where('is_resolved', false)->distinct('member_id')->count('member_id');
        $problemMembersAll = \App\Models\MemberCart::distinct('member_id')->count('member_id');

        $problemByCategory = [
            'umum' => \App\Models\MemberCart::whereHas('member', function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNull('job_category')
                       ->orWhere('job_category', 'like', '%UMUM%')
                       ->orWhere('job_category', 'like', '%PNS%')
                       ->orWhere('job_category', 'like', '%ASN%')
                       ->orWhere('job_category', 'like', '%SWASTA%');
                });
            })->count(),
            'pelajar' => \App\Models\MemberCart::whereHas('member', function ($q) {
                $q->where('job_category', 'like', '%PELAJAR%');
            })->count(),
            'mahasiswa' => \App\Models\MemberCart::whereHas('member', function ($q) {
                $q->where('job_category', 'like', '%MAHASISWA%');
            })->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Data Dashboard Admin berhasil dimuat.',
            'data'    => [
                'stats' => [
                    'total_catalogs'          => $totalCatalogs,
                    'new_catalogs_this_month' => $thisMonthCatalogs,
                    'isbn_verified'           => $isbnVerified,
                    'total_collections'       => $totalCollections,
                    'available_collections'   => $availableCollections,
                    'borrowed_collections'    => $borrowedCollections,
                    'total_quarantine'        => $totalQuarantine,
                    'missing_ddc'             => $missingDdc,
                    'digital_count'           => $digitalCount,
                    'total_members'           => $totalMembers,
                    'today_circulation'       => $todayCirculation,
                    'problem_cases'           => $totalProblemCases,
                    'active_problem_cases'    => $activeProblemCases,
                    'unique_problem_members'  => $uniqueProblemMembers,
                    'problem_members_all'     => $problemMembersAll,
                    'problem_by_category'     => $problemByCategory,
                ],
                'ddc' => [
                    'all' => $ddcItems,
                    'top' => $topDdc,
                ],
                'recent_activities' => $recentCatalogs,
                'physical_status'   => $physicalStatus,
                'top_books'         => $topBooks,
                'problem_stats'     => [
                    'total_cases'            => $totalProblemCases,
                    'active_cases'           => $activeProblemCases,
                    'unique_members'         => $uniqueProblemMembers,
                    'by_category'            => $problemByCategory,
                ],
            ],
        ], 200);
    }

    /**
     * Scan Barcode / RFID / Item Number / Member No
     */
    public function scan(Request $request): JsonResponse
    {
        $code = trim($request->input('code', ''));

        if (empty($code)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode barcode tidak boleh kosong.',
            ], 422);
        }

        // 1. Cek pada data Eksemplar Koleksi
        $collection = Collection::with('catalog')
            ->where('barcode_no', $code)
            ->orWhere('item_number', $code)
            ->orWhere('rfid_no', $code)
            ->first();

        if ($collection) {
            $catalog = $collection->catalog;
            $location = $collection->room_location_code ?: 'Rak Koleksi Umum A';
            $statusText = $collection->is_quarantine ? 'Dalam Karantina' : ($collection->availability_status === 'AVAILABLE' ? 'Tersedia di Rak' : 'Sedang Dipinjam');

            return response()->json([
                'success' => true,
                'type'    => 'collection',
                'message' => "Eksemplar \"{$catalog?->title}\" terdeteksi.",
                'data'    => [
                    'title'         => $catalog?->title,
                    'author'        => $catalog?->author_main,
                    'barcode_no'    => $collection->barcode_no,
                    'call_number'   => $collection->exemplar_call_number ?: $catalog?->catalog_call_number,
                    'location'      => $location,
                    'status'        => $statusText,
                    'is_quarantine' => (bool)$collection->is_quarantine,
                    'catalog_id'    => $catalog?->id,
                    'collection_id' => $collection->id,
                ],
            ], 200);
        }

        // 2. Cek pada data Anggota Perpustakaan
        $member = Member::where('member_no', $code)
            ->orWhere('phone_number', $code)
            ->orWhere('email', $code)
            ->first();

        if ($member) {
            return response()->json([
                'success' => true,
                'type'    => 'member',
                'message' => "Anggota \"{$member->name}\" ({$member->member_no}) terdeteksi.",
                'data'    => [
                    'name'        => $member->name,
                    'member_no'   => $member->member_no,
                    'institution' => $member->institution_name,
                    'category'    => $member->job_category,
                    'is_active'   => true,
                    'member_id'   => $member->id,
                ],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => "Barcode atau nomor identitas \"{$code}\" tidak ditemukan dalam database.",
        ], 404);
    }

    /**
     * Trigger synchronization and count recalibration
     */
    public function sync(): JsonResponse
    {
        $catalogs = Catalog::all();
        foreach ($catalogs as $cat) {
            $total = Collection::where('catalog_id', $cat->id)->count();
            $available = Collection::where('catalog_id', $cat->id)
                ->where('availability_status', 'AVAILABLE')
                ->where('is_quarantine', false)
                ->count();

            $cat->update([
                'total_copies'     => $total,
                'available_copies' => $available,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sinkronisasi data database DAPD Buleleng selesai! Jumlah eksemplar & ketersediaan berhasil diperbarui.',
        ], 200);
    }
}
