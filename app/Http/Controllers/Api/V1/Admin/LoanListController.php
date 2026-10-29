<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Modul Daftar Pinjaman Koleksi: pemantauan seluruh transaksi peminjaman aktif
 * (BORROWED / OVERDUE) beserta status batas waktu pinjamnya.
 */
class LoanListController extends Controller
{
    private const ACTIVE_STATUSES = [Loan::STATUS_BORROWED, Loan::STATUS_OVERDUE];

    private const SORTS = [
        'due_asc'   => ['due_date', 'asc'],
        'due_desc'  => ['due_date', 'desc'],
        'loan_desc' => ['loan_date', 'desc'],
        'loan_asc'  => ['loan_date', 'asc'],
    ];

    /**
     * GET /api/v1/admin/circulation/active-loans
     * Query: tab (all|overdue|on_time|due_today), search, job_category, date, date_field (loan_date|due_date),
     *        sort (due_asc|due_desc|loan_desc|loan_asc), per_page, page
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 10), 5), 100);

        $loans = $this->filteredQuery($request)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar pinjaman aktif berhasil diambil.',
            'stats'   => $this->stats(),
            'data'    => collect($loans->items())->map(fn ($loan) => $this->rowPayload($loan))->values(),
            'meta'    => [
                'current_page' => $loans->currentPage(),
                'last_page'    => $loans->lastPage(),
                'per_page'     => $loans->perPage(),
                'total'        => $loans->total(),
                'from'         => $loans->firstItem(),
                'to'           => $loans->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/circulation/active-loans/export
     * Filter sama dengan index, ditambah ids[] (opsional, hanya baris terpilih) dan format (csv|json).
     */
    public function export(Request $request): JsonResponse|StreamedResponse
    {
        $query = $this->filteredQuery($request);

        if ($request->filled('ids')) {
            $query->whereIn('loans.id', (array) $request->query('ids'));
        }

        $rows = $query->limit(5000)->get()->map(fn ($loan) => $this->rowPayload($loan));

        if ($request->query('format') === 'json') {
            return response()->json([
                'success' => true,
                'message' => 'Data laporan pinjaman berhasil diambil.',
                'stats'   => $this->stats(),
                'data'    => $rows->values(),
            ]);
        }

        $filename = 'daftar_pinjaman_dapd_buleleng_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'No. Peminjaman', 'Barcode', 'DDC / No. Panggil', 'Judul', 'Pengarang', 'Peminjam', 'No. Anggota',
                'Kategori', 'Instansi', 'Tanggal Pinjam', 'Jatuh Tempo', 'Status', 'Selisih Hari', 'Perpanjangan',
            ]);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['loan_number'], $row['barcode_no'], $row['call_number'], $row['title'], $row['author'],
                    $row['member']['name'], $row['member']['member_no'], $row['member']['job_category'],
                    $row['member']['institution_name'], $row['loan_date'], $row['due_date'],
                    $row['state_label'], $row['days_left'], $row['extension_count'],
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * POST /api/v1/admin/circulation/active-loans/sync
     * Menyelaraskan kolom status: BORROWED yang lewat jatuh tempo -> OVERDUE, dan sebaliknya.
     */
    public function sync(): JsonResponse
    {
        $today = now()->toDateString();

        $markedOverdue = Loan::where('status', Loan::STATUS_BORROWED)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->update(['status' => Loan::STATUS_OVERDUE]);

        $restored = Loan::where('status', Loan::STATUS_OVERDUE)
            ->where('due_date', '>=', $today)
            ->update(['status' => Loan::STATUS_BORROWED]);

        return response()->json([
            'success' => true,
            'message' => "Sinkronisasi selesai: {$markedOverdue} pinjaman ditandai terlambat, {$restored} dipulihkan.",
            'data'    => [
                'marked_overdue' => $markedOverdue,
                'restored'       => $restored,
                'stats'          => $this->stats(),
            ],
        ]);
    }

    private function activeQuery(): Builder
    {
        return Loan::whereIn('loans.status', self::ACTIVE_STATUSES);
    }

    private function filteredQuery(Request $request): Builder
    {
        $today = now()->toDateString();
        $query = $this->activeQuery()->with(['member', 'collection.catalog']);

        match ($request->query('tab', 'all')) {
            'overdue'   => $query->where('due_date', '<', $today),
            'due_today' => $query->whereDate('due_date', $today),
            'on_time'   => $query->where('due_date', '>', $today),
            default     => null,
        };

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('collection', function ($cq) use ($search) {
                        $cq->where('barcode_no', 'like', "%{$search}%")
                            ->orWhereHas('catalog', fn ($kq) => $kq->where('title', 'like', "%{$search}%"));
                    })
                    ->orWhereHas('member', function ($mq) use ($search) {
                        $mq->where('name', 'like', "%{$search}%")
                            ->orWhere('member_no', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('job_category') && $request->query('job_category') !== 'all') {
            $query->whereHas('member', fn ($mq) => $mq->where('job_category', $request->query('job_category')));
        }

        if ($request->filled('date')) {
            $dateField = $request->query('date_field') === 'due_date' ? 'due_date' : 'loan_date';
            $query->whereDate($dateField, $request->query('date'));
        }

        [$column, $direction] = self::SORTS[$request->query('sort')] ?? self::SORTS['due_asc'];

        return $query->orderBy($column, $direction)->orderBy('loans.id', 'desc');
    }

    private function stats(): array
    {
        $today = now()->toDateString();

        $total = $this->activeQuery()->count();
        $overdue = $this->activeQuery()->where('due_date', '<', $today)->count();
        $dueToday = $this->activeQuery()->whereDate('due_date', $today)->count();
        $onTime = $this->activeQuery()->where('due_date', '>', $today)->count();

        return [
            'total'             => $total,
            'on_time'           => $onTime,
            'due_today'         => $dueToday,
            'overdue'           => $overdue,
            'new_today'         => Loan::whereDate('loan_date', $today)->count(),
            'total_collections' => Collection::count(),
            'compliance_rate'   => $total > 0 ? round($onTime / $total * 100, 1) : 100.0,
        ];
    }

    private function rowPayload(Loan $loan): array
    {
        $collection = $loan->collection;
        $catalog = $collection?->catalog;
        $member = $loan->member;

        $today = now()->startOfDay();
        $daysLeft = $loan->due_date ? (int) $today->diffInDays($loan->due_date->copy()->startOfDay(), false) : null;

        [$state, $stateLabel] = match (true) {
            $daysLeft === null => ['unknown', 'Tanpa Jatuh Tempo'],
            $daysLeft < 0      => ['overdue', 'Terlambat'],
            $daysLeft === 0    => ['due_today', 'Jatuh Tempo Hari Ini'],
            default            => ['on_time', 'Tepat Waktu'],
        };

        return [
            'loan_id'         => $loan->id,
            'loan_number'     => $loan->loan_number,
            'status'          => $loan->status,
            'barcode_no'      => $collection?->barcode_no,
            'ddc_number'      => $catalog?->ddc_number,
            'call_number'     => $collection?->exemplar_call_number ?: $catalog?->catalog_call_number,
            'title'           => $catalog?->title,
            'author'          => $catalog?->author_main ?: $catalog?->author_corporate,
            'publisher'       => $catalog?->publisher,
            'publish_year'    => $catalog?->publish_year,
            'member'          => [
                'id'               => $member?->id,
                'name'             => $member?->name,
                'member_no'        => $member?->member_no,
                'job_category'     => $member?->job_category,
                'institution_name' => $member?->institution_name,
                'phone_number'     => $member?->phone_number,
            ],
            'loan_date'       => $loan->loan_date?->toDateString(),
            'due_date'        => $loan->due_date?->toDateString(),
            'extension_count' => (int) $loan->extension_count,
            'days_left'       => $daysLeft,
            'state'           => $state,
            'state_label'     => $stateLabel,
            'fine_estimate'   => $daysLeft !== null && $daysLeft < 0
                ? abs($daysLeft) * (float) get_setting('fine_per_day', 1000)
                : 0,
        ];
    }
}
