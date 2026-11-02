<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberCartResource;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MemberCart;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProblemCartController extends Controller
{
    /**
     * Display a listing of problem member cases with search, filters, and statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MemberCart::with(['member', 'loan', 'collection.catalog']);

        // 1. Pencarian (Search)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('item_title', 'like', "%{$search}%")
                  ->orWhere('item_barcode', 'like', "%{$search}%")
                  ->orWhere('item_call_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('handled_by', 'like', "%{$search}%")
                  ->orWhereHas('member', function ($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%")
                        ->orWhere('member_no', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('institution_name', 'like', "%{$search}%");
                  });
            });
        }

        // 2. Filter Jenis Masalah / Pelanggaran
        if ($request->filled('problem_type') && $request->problem_type !== 'all' && $request->problem_type !== 'ALL') {
            $problemType = strtoupper(trim($request->problem_type));
            $query->where('problem_type', $problemType);
        }

        // 3. Filter Kategori Anggota (Umum, Pelajar, Mahasiswa)
        if ($request->filled('job_category') && $request->job_category !== 'all' && $request->job_category !== 'ALL') {
            $jobCategory = strtoupper(trim($request->job_category));
            $query->whereHas('member', function ($mq) use ($jobCategory) {
                if (str_contains($jobCategory, 'UMUM')) {
                    $mq->where('job_category', 'like', '%UMUM%');
                } elseif (str_contains($jobCategory, 'PELAJAR')) {
                    $mq->where('job_category', 'like', '%PELAJAR%');
                } elseif (str_contains($jobCategory, 'MAHASISWA')) {
                    $mq->where('job_category', 'like', '%MAHASISWA%');
                } else {
                    $mq->where('job_category', $jobCategory);
                }
            });
        }

        // 4. Filter Status Penanganan (Hanya Akses Dibekukan atau Selesai)
        if ($request->filled('status') && $request->status !== 'all' && $request->status !== 'ALL') {
            $status = strtoupper(trim($request->status));
            if ($status === 'BELUM_SELESAI' || $status === 'UNRESOLVED' || $status === 'AKSES_DIBEKUKAN') {
                $query->where('is_resolved', false);
            } elseif ($status === 'SELESAI' || $status === 'RESOLVED') {
                $query->where('is_resolved', true);
            } else {
                $query->where('action_status', $status);
            }
        }

        // 5. Sorting & Pagination
        $perPage = (int) $request->get('per_page', 10);
        if ($perPage < 1) $perPage = 10;
        if ($perPage > 100) $perPage = 100;

        $cases = $query->orderBy('is_resolved', 'asc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        // 6. Hitung Statistik Realtime dari Database
        $allCasesQuery = MemberCart::query();
        $totalCases = $allCasesQuery->count();
        $uniqueMembersCount = MemberCart::distinct('member_id')->count('member_id');

        $umumCount = MemberCart::whereHas('member', function ($q) {
            $q->where('job_category', 'like', '%UMUM%');
        })->count();

        $pelajarCount = MemberCart::whereHas('member', function ($q) {
            $q->where('job_category', 'like', '%PELAJAR%');
        })->count();

        $mahasiswaCount = MemberCart::whereHas('member', function ($q) {
            $q->where('job_category', 'like', '%MAHASISWA%');
        })->count();

        $calcPercent = function ($count, $total) {
            if ($total <= 0) return '0.0%';
            return number_format(($count / $total) * 100, 1) . '%';
        };

        // Status counts (Akses tindakan hanya Akses Dibekukan dan Selesai)
        $unresolvedCount = MemberCart::where('is_resolved', false)->count();
        $resolvedCount = MemberCart::where('is_resolved', true)->count();

        // Problem type counts
        $bukuHilangCount = MemberCart::where('problem_type', 'BUKU_HILANG')->count();
        $telatCount = MemberCart::whereIn('problem_type', ['TELAT', 'DENDA_MENUMPUK'])->count();
        $rusakCount = MemberCart::where('problem_type', 'KOLEKSI_RUSAK')->count();
        $anomaliCount = MemberCart::where('problem_type', 'ANOMALI_IDENTITAS')->count();

        return response()->json([
            'success' => true,
            'message' => 'Daftar keranjang anggota bermasalah berhasil dimuat.',
            'data'    => MemberCartResource::collection($cases)->response()->getData(true),
            'stats'   => [
                'total_cases'           => $totalCases,
                'unique_members'        => $uniqueMembersCount,
                'unique_members_count'  => $uniqueMembersCount,
                'umum_count'            => $umumCount,
                'umum_percent'          => $calcPercent($umumCount, $totalCases),
                'pelajar_count'         => $pelajarCount,
                'pelajar_percent'       => $calcPercent($pelajarCount, $totalCases),
                'mahasiswa_count'       => $mahasiswaCount,
                'mahasiswa_percent'     => $calcPercent($mahasiswaCount, $totalCases),
                'status_breakdown'      => [
                    'all'                   => $totalCases,
                    'unresolved'            => $unresolvedCount,
                    'suspended'             => $unresolvedCount,
                    'resolved'              => $resolvedCount,
                ],
                'problem_type_breakdown'=> [
                    'all'            => $totalCases,
                    'buku_hilang'    => $bukuHilangCount,
                    'telat'          => $telatCount,
                    'koleksi_rusak'  => $rusakCount,
                    'anomali'        => $anomaliCount,
                ],
            ],
        ]);
    }

    /**
     * Store a newly created problem case into member_carts.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'member_id'          => ['required', 'exists:members,id'],
            'problem_type'       => ['required', 'string', 'max:100'],
            'item_title'         => ['nullable', 'string', 'max:255'],
            'item_call_number'   => ['nullable', 'string', 'max:100'],
            'item_barcode'       => ['nullable', 'string', 'max:100'],
            'collection_id'      => ['nullable', 'exists:collections,id'],
            'loan_id'            => ['nullable', 'exists:loans,id'],
            'fine_amount'        => ['nullable', 'numeric', 'min:0'],
            'paid_amount'        => ['nullable', 'numeric', 'min:0'],
            'handled_by'         => ['nullable', 'string', 'max:255'],
            'action_status'      => ['nullable', 'string', 'max:100'],
            'action_notes'       => ['nullable', 'string'],
            'late_duration_text' => ['nullable', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'reported_date'      => ['nullable', 'date'],
        ], [
            'member_id.required'    => 'Anggota perpustakaan wajib dipilih.',
            'member_id.exists'      => 'Data anggota tidak ditemukan dalam database.',
            'problem_type.required' => 'Jenis permasalahan wajib ditentukan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Pencegahan pencatatan duplikat untuk loan_id dan problem_type yang sama
        if (!empty($validated['loan_id'])) {
            $duplicate = MemberCart::where('loan_id', $validated['loan_id'])
                ->where('problem_type', $validated['problem_type'])
                ->where('is_resolved', false)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => "Kasus serupa untuk peminjaman ini sudah tercatat dalam Keranjang Bermasalah (ID #{$duplicate->id}).",
                ], 422);
            }
        }

        $officer = !empty($validated['handled_by']) ? $validated['handled_by'] : 'Petugas Sirkulasi';
        $actionStatus = !empty($validated['action_status']) ? $validated['action_status'] : 'AKSES_DIBEKUKAN';
        $reportedDate = !empty($validated['reported_date']) ? $validated['reported_date'] : Carbon::today()->format('Y-m-d');

        // Otomatis buat histori tindak lanjut awal
        $history = [
            [
                'timestamp' => Carbon::now()->toIso8601String(),
                'date'      => Carbon::now()->translatedFormat('d F Y, H:i'),
                'officer'   => $officer,
                'action'    => 'Pencatatan Kasus',
                'status'    => $actionStatus,
                'notes'     => !empty($validated['description']) ? $validated['description'] : 'Kasus anggota dimasukkan ke dalam keranjang pengawasan sirkulasi.',
            ]
        ];

        // Jika ada collection_id, lengkapi data buku dari koleksi
        $collectionId = $validated['collection_id'] ?? null;
        $itemTitle = $validated['item_title'] ?? null;
        $itemBarcode = $validated['item_barcode'] ?? null;
        $itemCallNo = $validated['item_call_number'] ?? null;

        if ($collectionId) {
            $col = Collection::with('catalog')->find($collectionId);
            if ($col) {
                $itemBarcode = $itemBarcode ?: $col->barcode_no;
                $itemCallNo = $itemCallNo ?: ($col->exemplar_call_number ?: $col->catalog?->catalog_call_number);
                $itemTitle = $itemTitle ?: $col->catalog?->title;
            }
        }

        $cart = MemberCart::create([
            'member_id'          => $validated['member_id'],
            'loan_id'            => $validated['loan_id'] ?? null,
            'collection_id'      => $collectionId,
            'problem_type'       => strtoupper(trim($validated['problem_type'])),
            'item_title'         => $itemTitle,
            'item_call_number'   => $itemCallNo,
            'item_barcode'       => $itemBarcode,
            'description'        => $validated['description'] ?? null,
            'fine_amount'        => 0, // Tidak ada denda uang
            'paid_amount'        => 0,
            'handled_by'         => $officer,
            'action_status'      => $actionStatus,
            'action_notes'       => $validated['action_notes'] ?? null,
            'reported_date'      => $reportedDate,
            'late_duration_text' => $validated['late_duration_text'] ?? 'Perlu Tindak Lanjut',
            'followup_history'   => $history,
            'is_resolved'        => false,
        ]);

        // Perbarui status anggota di tabel members menjadi Akses Dibekukan
        $member = Member::find($validated['member_id']);
        if ($member) {
            $member->update(['status' => 'Akses Dibekukan']);
        }

        $cart->load(['member', 'loan', 'collection.catalog']);

        return response()->json([
            'success' => true,
            'message' => "Kasus permasalahan anggota {$cart->member?->name} berhasil dicatat.",
            'data'    => new MemberCartResource($cart),
        ], 201);
    }

    /**
     * Show detail of a single problem case.
     */
    public function show(int $id): JsonResponse
    {
        $cart = MemberCart::with(['member.loans', 'loan', 'collection.catalog'])->find($id);

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Data kasus permasalahan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new MemberCartResource($cart),
        ]);
    }

    /**
     * Update an existing problem case.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $cart = MemberCart::find($id);

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Data kasus tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'problem_type'       => ['sometimes', 'string'],
            'item_title'         => ['nullable', 'string', 'max:255'],
            'item_call_number'   => ['nullable', 'string', 'max:100'],
            'item_barcode'       => ['nullable', 'string', 'max:100'],
            'fine_amount'        => ['nullable', 'numeric', 'min:0'],
            'paid_amount'        => ['nullable', 'numeric', 'min:0'],
            'handled_by'         => ['nullable', 'string', 'max:255'],
            'action_status'      => ['nullable', 'string', 'max:100'],
            'action_notes'       => ['nullable', 'string'],
            'late_duration_text' => ['nullable', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Jika ada perubahan status atau penugasan petugas, catat ke followup_history
        $history = is_array($cart->followup_history) ? $cart->followup_history : [];
        if (!empty($data['action_status']) && $data['action_status'] !== $cart->action_status) {
            $history[] = [
                'timestamp' => Carbon::now()->toIso8601String(),
                'date'      => Carbon::now()->translatedFormat('d F Y, H:i'),
                'officer'   => $data['handled_by'] ?? $cart->handled_by,
                'action'    => 'Perubahan Status Tindakan',
                'status'    => $data['action_status'],
                'notes'     => $data['action_notes'] ?? "Status diubah menjadi {$data['action_status']}",
            ];
            $data['followup_history'] = $history;
        }

        $cart->update($data);
        $cart->load(['member', 'loan', 'collection.catalog']);

        return response()->json([
            'success' => true,
            'message' => 'Data kasus permasalahan berhasil diperbarui.',
            'data'    => new MemberCartResource($cart),
        ]);
    }

    /**
     * Add a follow-up action to a problem case (Riwayat Tindak Lanjut).
     */
    public function followup(Request $request, int $id): JsonResponse
    {
        $cart = MemberCart::find($id);

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Data kasus tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'action'        => ['required', 'string', 'max:255'],
            'officer'       => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string'],
            'payment_add'   => ['nullable', 'numeric', 'min:0'],
            'payment_added' => ['nullable', 'numeric', 'min:0'],
            'action_status' => ['nullable', 'string'],
            'action_notes'  => ['nullable', 'string'],
        ], [
            'action.required' => 'Jenis tindakan tindak lanjut wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();
        $officer = !empty($validated['officer']) ? $validated['officer'] : ($cart->handled_by ?: 'Petugas Sirkulasi');
        $paymentAdd = (float) ($validated['payment_add'] ?? ($validated['payment_added'] ?? 0));

        $newPaidAmount = (float) $cart->paid_amount + $paymentAdd;

        $history = is_array($cart->followup_history) ? $cart->followup_history : [];
        $history[] = [
            'timestamp'    => Carbon::now()->toIso8601String(),
            'date'         => Carbon::now()->translatedFormat('d F Y, H:i'),
            'officer'      => $officer,
            'action'       => $validated['action'],
            'payment_made' => $paymentAdd > 0 ? $paymentAdd : null,
            'status'       => $validated['action_status'] ?? $cart->action_status,
            'notes'        => $validated['notes'] ?? ($paymentAdd > 0 ? "Pembayaran cicilan Rp " . number_format($paymentAdd, 0, ',', '.') : '-'),
        ];

        $updateData = [
            'handled_by'       => $officer,
            'paid_amount'      => $newPaidAmount,
            'followup_history' => $history,
        ];

        if (!empty($validated['action_status'])) {
            $updateData['action_status'] = $validated['action_status'];
        }
        if (!empty($validated['action_notes'])) {
            $updateData['action_notes'] = $validated['action_notes'];
        }

        // Jika cicilan lunas dan statusnya dalam mediasi, sesuaikan catatan
        if ($cart->fine_amount > 0 && $newPaidAmount >= $cart->fine_amount) {
            $updateData['action_notes'] = 'Tagihan denda / ganti rugi lunas';
        }

        $cart->update($updateData);
        $cart->load(['member', 'loan', 'collection.catalog']);

        return response()->json([
            'success' => true,
            'message' => "Tindak lanjut '{$validated['action']}' berhasil dicatat.",
            'data'    => new MemberCartResource($cart),
        ]);
    }

    /**
     * Mark a problem case as resolved (Penyelesaian Kasus).
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $cart = MemberCart::with('member')->find($id);

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Data kasus tidak ditemukan.',
            ], 404);
        }

        $resolutionNotes = $request->input('notes', 'Kasus telah diselesaikan dan sanksi pemustaka diangkat.');
        $officer = $request->input('officer', $cart->handled_by ?: 'Petugas Sirkulasi');

        $history = is_array($cart->followup_history) ? $cart->followup_history : [];
        $history[] = [
            'timestamp' => Carbon::now()->toIso8601String(),
            'date'      => Carbon::now()->translatedFormat('d F Y, H:i'),
            'officer'   => $officer,
            'action'    => 'Penyelesaian Kasus',
            'status'    => 'SELESAI',
            'notes'     => $resolutionNotes,
        ];

        $cart->update([
            'is_resolved'      => true,
            'action_status'    => 'SELESAI',
            'action_notes'     => 'Hak pinjam dipulihkan',
            'resolution_notes' => $resolutionNotes,
            'resolved_at'      => Carbon::now(),
            'followup_history' => $history,
        ]);

        // Periksa apakah anggota masih memiliki kasus bermasalah aktif lainnya
        $otherActiveCases = MemberCart::where('member_id', $cart->member_id)
            ->where('id', '!=', $cart->id)
            ->where('is_resolved', false)
            ->exists();

        // Jika tidak ada kasus aktif lain, pulihkan status keanggotaan
        if (!$otherActiveCases && $cart->member) {
            $cart->member->update(['status' => 'Aktif Bebas Pustaka']);
        }

        $cart->load(['member', 'loan', 'collection.catalog']);

        return response()->json([
            'success' => true,
            'message' => "Kasus permasalahan anggota {$cart->member?->name} berhasil ditandai selesai.",
            'data'    => new MemberCartResource($cart),
        ]);
    }

    /**
     * Bulk actions on selected problem cases.
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $action = strtoupper(trim($request->input('action', '')));

        if (empty($ids) || !is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih setidaknya satu baris kasus.',
            ], 422);
        }

        $cases = MemberCart::with('member')->whereIn('id', $ids)->get();
        $count = $cases->count();

        if ($action === 'FREEZE') {
            foreach ($cases as $c) {
                $c->update([
                    'action_status' => 'AKSES_DIBEKUKAN',
                    'action_notes'  => 'Akses peminjaman dibekukan sementara oleh petugas',
                ]);
                if ($c->member) {
                    $c->member->update(['status' => 'Akses Dibekukan']);
                }
            }
            $msg = "Akses pinjam {$count} anggota berhasil dibekukan.";
        } elseif ($action === 'UNFREEZE') {
            foreach ($cases as $c) {
                $c->update([
                    'action_status' => 'MENUNGGU_KONFIRMASI',
                    'action_notes'  => 'Pembekuan dicabut, pemustaka diminta konfirmasi layanan',
                ]);
            }
            $msg = "Akses {$count} anggota berhasil dipulihkan dari pembekuan.";
        } elseif ($action === 'RESOLVE') {
            foreach ($cases as $c) {
                $c->update([
                    'is_resolved'      => true,
                    'action_status'    => 'SELESAI',
                    'action_notes'     => 'Hak pinjam dipulihkan massal',
                    'resolved_at'      => Carbon::now(),
                    'resolution_notes' => 'Diselesaikan melalui tindakan massal pustakawan.',
                ]);
                if ($c->member) {
                    $hasOther = MemberCart::where('member_id', $c->member_id)
                        ->whereNotIn('id', $ids)
                        ->where('is_resolved', false)
                        ->exists();
                    if (!$hasOther) {
                        $c->member->update(['status' => 'Aktif Bebas Pustaka']);
                    }
                }
            }
            $msg = "{$count} kasus anggota bermasalah berhasil ditandai selesai.";
        } elseif ($action === 'DELETE') {
            MemberCart::whereIn('id', $ids)->delete();
            $msg = "{$count} data kasus berhasil dihapus dari keranjang.";
        } else {
            return response()->json([
                'success' => false,
                'message' => "Tindakan massal '{$action}' tidak dikenali.",
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'affected_count' => $count,
        ]);
    }

    /**
     * Delete a single problem case.
     */
    public function destroy(int $id): JsonResponse
    {
        $cart = MemberCart::find($id);

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Kasus tidak ditemukan.',
            ], 404);
        }

        $memberId = $cart->member_id;
        $cart->delete();

        // Pulihkan status member jika tidak ada kasus aktif tersisa
        $otherActive = MemberCart::where('member_id', $memberId)->where('is_resolved', false)->exists();
        if (!$otherActive) {
            $member = Member::find($memberId);
            if ($member && str_contains(strtolower($member->status), 'dibekukan')) {
                $member->update(['status' => 'Aktif Bebas Pustaka']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data kasus berhasil dihapus dari keranjang bermasalah.',
        ]);
    }

    /**
     * Synchronize circulation data (Overdue loans & damaged collections) into problem carts.
     */
    public function syncCirculation(): JsonResponse
    {
        $today = Carbon::today();
        $newCases = 0;

        // 1. Deteksi Peminjaman Overdue / Terlambat
        $overdueLoans = Loan::with(['member', 'collection.catalog'])
            ->whereIn('status', [Loan::STATUS_BORROWED, Loan::STATUS_OVERDUE])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->get();

        foreach ($overdueLoans as $loan) {
            if (!$loan->member) continue;

            $existing = MemberCart::where('loan_id', $loan->id)
                ->where('is_resolved', false)
                ->first();

            if (!$existing) {
                $days = Carbon::parse($loan->due_date)->diffInDays($today);
                $fineRate = (float) get_setting('fine_per_day', 1000);
                $calculatedFine = $days * $fineRate;

                MemberCart::create([
                    'member_id'          => $loan->member_id,
                    'loan_id'            => $loan->id,
                    'collection_id'      => $loan->collection_id,
                    'problem_type'       => 'TELAT',
                    'item_title'         => $loan->collection?->catalog?->title ?: 'Koleksi Buku Terlambat',
                    'item_call_number'   => $loan->collection?->exemplar_call_number ?: $loan->collection?->catalog?->catalog_call_number,
                    'item_barcode'       => $loan->collection?->barcode_no,
                    'description'        => "Keterlambatan pengembalian buku melewati tanggal jatuh tempo ({$loan->due_date->format('d/m/Y')}).",
                    'fine_amount'        => 0,
                    'paid_amount'        => 0,
                    'handled_by'         => 'Sistem Otomasi Sirkulasi',
                    'action_status'      => 'AKSES_DIBEKUKAN',
                    'action_notes'       => "Akses peminjaman dibekukan (Telat {$days} Hari)",
                    'reported_date'      => $loan->due_date->format('Y-m-d'),
                    'late_duration_text' => "Telat {$days} Hari",
                    'followup_history'   => [
                        [
                            'timestamp' => Carbon::now()->toIso8601String(),
                            'date'      => Carbon::now()->translatedFormat('d F Y, H:i'),
                            'officer'   => 'Sistem Otomasi Sirkulasi',
                            'action'    => 'Deteksi Keterlambatan Otomatis',
                            'status'    => 'AKSES_DIBEKUKAN',
                            'notes'     => "Terlambat {$days} hari sejak jatuh tempo. Akses pinjam dibekukan.",
                        ]
                    ],
                    'is_resolved'        => false,
                ]);

                if ($loan->member) {
                    $loan->member->update(['status' => 'Akses Dibekukan']);
                }

                $newCases++;
            }
        }

        // 2. Deteksi Koleksi Rusak / Karantina yang terhubung dengan peminjaman terakhir
        $damagedCollections = Collection::with('catalog')
            ->where(function ($q) {
                $q->where('is_quarantine', true)
                  ->orWhereIn('physical_condition', ['Rusak', 'Rusak Berat', 'Hilang', 'DAMAGED', 'LOST']);
            })
            ->get();

        foreach ($damagedCollections as $col) {
            $lastLoan = Loan::with('member')
                ->where('collection_id', $col->id)
                ->latest('id')
                ->first();

            if ($lastLoan && $lastLoan->member) {
                $isLost = in_array(strtolower($col->physical_condition ?? ''), ['hilang', 'lost']);
                $type = $isLost ? 'BUKU_HILANG' : 'KOLEKSI_RUSAK';

                $existing = MemberCart::where('collection_id', $col->id)
                    ->where('problem_type', $type)
                    ->where('is_resolved', false)
                    ->first();

                if (!$existing) {
                    MemberCart::create([
                        'member_id'          => $lastLoan->member_id,
                        'loan_id'            => $lastLoan->id,
                        'collection_id'      => $col->id,
                        'problem_type'       => $type,
                        'item_title'         => $col->catalog?->title ?: 'Eksemplar ' . $col->barcode_no,
                        'item_call_number'   => $col->exemplar_call_number ?: $col->catalog?->catalog_call_number,
                        'item_barcode'       => $col->barcode_no,
                        'description'        => $col->quarantine_notes ?: ($col->quarantine_reason ?: "Koleksi eksemplar {$col->barcode_no} tercatat dalam kondisi {$col->physical_condition}."),
                        'fine_amount'        => 0,
                        'paid_amount'        => 0,
                        'handled_by'         => 'Petugas Karantina & Sirkulasi',
                        'action_status'      => 'AKSES_DIBEKUKAN',
                        'action_notes'       => $isLost ? 'Menunggu buku pengganti' : 'Akses pinjam dibekukan sementara',
                        'reported_date'      => Carbon::today()->format('Y-m-d'),
                        'late_duration_text' => $isLost ? 'Buku Hilang' : 'Koleksi Rusak Berat',
                        'followup_history'   => [
                            [
                                'timestamp' => Carbon::now()->toIso8601String(),
                                'date'      => Carbon::now()->translatedFormat('d F Y, H:i'),
                                'officer'   => 'Petugas Karantina',
                                'action'    => 'Pencatatan dari Pemeriksaan Fisik',
                                'status'    => 'AKSES_DIBEKUKAN',
                                'notes'     => "Eksemplar masuk status {$col->physical_condition}. Akses peminjaman dibekukan.",
                            ]
                        ],
                        'is_resolved'        => false,
                    ]);

                    $lastLoan->member->update(['status' => 'Akses Dibekukan']);

                    $newCases++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Sinkronisasi sirkulasi selesai. {$newCases} kasus baru terdeteksi dan dicatat.",
            'new_cases_count' => $newCases,
        ]);
    }

    /**
     * Get problem history for a specific member (for Member Profile).
     */
    public function memberHistory(int $memberId): JsonResponse
    {
        $member = Member::find($memberId);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Data anggota tidak ditemukan.',
            ], 404);
        }

        $cases = MemberCart::with(['loan', 'collection.catalog'])
            ->where('member_id', $memberId)
            ->orderBy('is_resolved', 'asc')
            ->orderBy('id', 'desc')
            ->get();

        $activeCasesCount = $cases->where('is_resolved', false)->count();
        $resolvedCasesCount = $cases->where('is_resolved', true)->count();
        $totalFines = $cases->sum('fine_amount');
        $totalPaid = $cases->sum('paid_amount');

        return response()->json([
            'success' => true,
            'message' => "Riwayat permasalahan anggota {$member->name} berhasil dimuat.",
            'data'    => MemberCartResource::collection($cases),
            'summary' => [
                'active_cases_count'   => $activeCasesCount,
                'resolved_cases_count' => $resolvedCasesCount,
                'total_fines'          => (float) $totalFines,
                'total_paid'           => (float) $totalPaid,
                'remaining_debt'       => (float) max(0, $totalFines - $totalPaid),
                'is_restricted'        => $activeCasesCount > 0,
            ],
        ]);
    }

    /**
     * Get problem history for current logged in member.
     */
    public function myProblemHistory(Request $request): JsonResponse
    {
        $member = $request->user();
        if (!$member || !($member instanceof Member)) {
            // Coba dari auth sanctum atau member guard
            $member = \Illuminate\Support\Facades\Auth::guard('sanctum')->user()
                ?? \Illuminate\Support\Facades\Auth::guard('member')->user();
        }

        if (!$member || !($member instanceof Member)) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi pemustaka tidak valid.',
            ], 401);
        }

        return $this->memberHistory($member->id);
    }
}

