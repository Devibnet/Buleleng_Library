<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MemberCart;
use App\Services\CirculationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Modul Pengembalian Buku (Loket Sirkulasi) untuk Dashboard Admin SPA.
 * Alur: scan barcode eksemplar / ID anggota -> pilih koleksi -> selesaikan pengembalian (batch).
 */
class ReturnController extends Controller
{
    private const ACTIVE_STATUSES = [Loan::STATUS_BORROWED, Loan::STATUS_OVERDUE];

    public function __construct(protected CirculationService $circulationService)
    {
    }

    /**
     * GET /api/v1/admin/circulation/return/lookup?code=...
     * Kode dapat berupa barcode eksemplar (mengembalikan pinjaman buku tersebut)
     * atau nomor anggota / NIK / email (mengembalikan seluruh pinjaman aktif anggota).
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);
        $code = trim($request->query('code'));

        $collection = Collection::where('barcode_no', $code)->first();

        if ($collection) {
            $loan = Loan::with(['member', 'collection.catalog'])
                ->where('collection_id', $collection->id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->latest('id')
                ->first();

            if (! $loan) {
                return response()->json([
                    'success' => false,
                    'message' => "Buku dengan barcode '{$code}' tidak sedang dipinjam (Status: {$collection->availability_status}).",
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaksi peminjaman ditemukan.',
                'data'    => [
                    'type'            => 'collection',
                    'scanned_barcode' => $collection->barcode_no,
                    'member'          => $this->memberPayload($loan->member),
                    'loans'           => [$this->loanPayload($loan)],
                ],
            ]);
        }

        $member = Member::where('member_no', $code)
            ->orWhere('nik', $code)
            ->orWhere('email', $code)
            ->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => "Barcode buku atau ID anggota '{$code}' tidak ditemukan.",
            ], 404);
        }

        $loans = Loan::with(['collection.catalog'])
            ->where('member_id', $member->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('due_date')
            ->get();

        return response()->json([
            'success' => true,
            'message' => $loans->isEmpty()
                ? "{$member->name} tidak memiliki pinjaman aktif."
                : 'Daftar pinjaman aktif anggota ditemukan.',
            'data'    => [
                'type'            => 'member',
                'scanned_barcode' => null,
                'member'          => $this->memberPayload($member),
                'loans'           => $loans->map(fn ($loan) => $this->loanPayload($loan))->values(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/circulation/return
     * Body: { member_no, items: [{ barcode, condition }] }
     * Seluruh eksemplar diproses dalam satu transaksi database (gagal satu = batal semua).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_no'         => 'required|string',
            'items'             => 'required|array|min:1',
            'items.*.barcode'   => 'required|string|distinct',
            'items.*.condition' => 'nullable|string|in:Baik,Rusak Ringan,Rusak Berat',
        ], [
            'items.required'           => 'Pilih minimal satu koleksi yang dikembalikan.',
            'items.*.barcode.distinct' => 'Terdapat barcode buku yang ganda.',
            'items.*.condition.in'     => 'Kondisi buku tidak valid.',
        ]);

        $operatorId = $request->user()?->id;

        try {
            $loans = DB::transaction(function () use ($validated, $operatorId) {
                return collect($validated['items'])->map(fn ($item) => $this->circulationService->processReturn(
                    $validated['member_no'],
                    $item['barcode'],
                    $operatorId,
                    $item['condition'] ?? 'Baik'
                ));
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $loans->each->load(['member', 'collection.catalog', 'returnedByAdmin']);
        $totalFine = (float) $loans->sum('fine_amount');

        $message = "Pengembalian {$loans->count()} eksemplar berhasil diproses.";
        if ($totalFine > 0) {
            $message .= ' Total denda keterlambatan: Rp ' . number_format($totalFine, 0, ',', '.') . '.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'member'     => $this->memberPayload($loans->first()->member),
                'loans'      => LoanResource::collection($loans),
                'total_fine' => $totalFine,
            ],
        ], 201);
    }

    private function memberPayload(Member $member): array
    {
        $openProblems = MemberCart::where('member_id', $member->id)->where('is_resolved', false)->count();

        return [
            'id'                => $member->id,
            'member_no'         => $member->member_no,
            'name'              => $member->name,
            'job_category'      => $member->job_category,
            'institution_name'  => $member->institution_name,
            'status'            => $member->status ?: 'Aktif Bebas Pustaka',
            'open_problems'     => $openProblems,
            'sanction_free'     => $openProblems === 0,
        ];
    }

    private function loanPayload(Loan $loan): array
    {
        $collection = $loan->collection;
        $catalog = $collection?->catalog;

        $today = now()->startOfDay();
        $dueDate = $loan->due_date?->copy()->startOfDay();
        $lateDays = $dueDate && $today->greaterThan($dueDate) ? (int) $dueDate->diffInDays($today) : 0;

        return [
            'loan_id'       => $loan->id,
            'loan_number'   => $loan->loan_number,
            'barcode_no'    => $collection?->barcode_no,
            'title'         => $catalog?->title,
            'author'        => $catalog?->author_main ?: $catalog?->author_corporate,
            'publisher'     => $catalog?->publisher,
            'publish_year'  => $catalog?->publish_year,
            'call_number'   => $collection?->exemplar_call_number ?: $catalog?->catalog_call_number,
            'shelf'         => $collection?->room_location_code,
            'loan_date'     => $loan->loan_date?->toDateString(),
            'due_date'      => $loan->due_date?->toDateString(),
            'late_days'     => $lateDays,
            'fine_estimate' => $lateDays * (float) get_setting('fine_per_day', 1000),
        ];
    }
}
