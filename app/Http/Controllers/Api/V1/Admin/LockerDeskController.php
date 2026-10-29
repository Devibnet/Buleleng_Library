<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Locker;
use App\Models\LockerLoan;
use App\Models\Member;
use App\Services\LockerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modul Peminjaman Loker Penitipan (Loket Ruang Baca) untuk Dashboard Admin SPA.
 * Alur: identifikasi anggota -> scan / pilih kunci loker -> konfirmasi & serahkan kunci.
 */
class LockerDeskController extends Controller
{
    private const ACTIVE_STATUSES = ['BORROWED', 'ACTIVE'];

    private const LOCAL_TIMEZONE = 'Asia/Makassar';

    public function __construct(protected LockerService $lockerService)
    {
    }

    /**
     * GET /api/v1/admin/lockers/desk/member?code=...
     */
    public function member(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);
        $code = trim($request->query('code'));

        $member = Member::where('member_no', $code)
            ->orWhere('nik', $code)
            ->orWhere('email', $code)
            ->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => "Anggota dengan nomor '{$code}' tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data anggota berhasil diambil.',
            'data'    => $this->memberPayload($member),
        ]);
    }

    /**
     * GET /api/v1/admin/lockers/desk/lockers
     * Daftar seluruh loker beserta statusnya (untuk pilihan cepat tanpa scan).
     */
    public function lockers(): JsonResponse
    {
        $lockers = Locker::orderBy('locker_code')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar loker berhasil diambil.',
            'data'    => [
                'lockers' => $lockers->map(fn ($locker) => $this->lockerPayload($locker))->values(),
                'summary' => [
                    'total'     => $lockers->count(),
                    'available' => $lockers->where('status', 'AVAILABLE')->count(),
                ],
                'service' => $this->serviceTimes(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/lockers/desk/locker?code=...
     */
    public function locker(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);
        $code = trim($request->query('code'));

        $locker = Locker::where('locker_code', $code)->first();

        if (! $locker) {
            return response()->json([
                'success' => false,
                'message' => "Loker dengan kode '{$code}' tidak ditemukan.",
            ], 404);
        }

        if ($locker->status !== 'AVAILABLE') {
            return response()->json([
                'success' => false,
                'message' => "Loker {$locker->locker_code} sedang tidak tersedia (Status: {$locker->status}).",
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Loker tersedia.',
            'data'    => $this->lockerPayload($locker),
        ]);
    }

    /**
     * POST /api/v1/admin/lockers/desk/borrow
     * Body: { member_no, locker_code, deposit_type }
     */
    public function borrow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_no'    => 'required|string',
            'locker_code'  => 'required|string',
            'deposit_type' => 'nullable|string|max:100',
        ], [
            'member_no.required'   => 'Identifikasi anggota terlebih dahulu.',
            'locker_code.required' => 'Pilih atau scan kunci loker terlebih dahulu.',
        ]);

        try {
            $loan = $this->lockerService->borrowLocker(
                $validated['member_no'],
                $validated['locker_code'],
                $validated['deposit_type'] ?? 'KTP',
                $request->user()?->id
            );
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $loan->load(['member', 'locker', 'processedByAdmin']);

        return response()->json([
            'success' => true,
            'message' => "Kunci loker {$loan->locker->locker_code} diserahkan kepada {$loan->member->name}. No. Transaksi: {$loan->loan_number}.",
            'data'    => [
                'loan_number'  => $loan->loan_number,
                'deposit_type' => $loan->deposit_type,
                'borrow_date'  => $loan->borrow_date?->toIso8601String(),
                'processed_by' => $loan->processedByAdmin?->name,
                'member'       => $this->memberPayload($loan->member),
                'locker'       => $this->lockerPayload($loan->locker),
                'service'      => $this->serviceTimes(),
            ],
        ], 201);
    }

    private function memberPayload(Member $member): array
    {
        $activeLoan = LockerLoan::with('locker')
            ->where('member_id', $member->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('id')
            ->first();

        return [
            'id'               => $member->id,
            'member_no'        => $member->member_no,
            'name'             => $member->name,
            'job_category'     => $member->job_category,
            'institution_name' => $member->institution_name,
            'status'           => $member->status ?: 'Aktif Bebas Pustaka',
            'active_locker'    => $activeLoan ? [
                'loan_number' => $activeLoan->loan_number,
                'locker_code' => $activeLoan->locker?->locker_code,
                'borrow_date' => $activeLoan->borrow_date?->toIso8601String(),
            ] : null,
            'eligible'         => $activeLoan === null,
            'block_reason'     => $activeLoan
                ? "Anggota masih memegang kunci loker {$activeLoan->locker?->locker_code} (No. {$activeLoan->loan_number}). Kembalikan terlebih dahulu."
                : null,
        ];
    }

    private function lockerPayload(Locker $locker): array
    {
        preg_match('/(\d+)\s*$/', $locker->locker_code, $matches);

        return [
            'id'          => $locker->id,
            'locker_code' => $locker->locker_code,
            'number'      => isset($matches[1]) ? (int) $matches[1] : null,
            'status'      => $locker->status,
        ];
    }

    private function serviceTimes(): array
    {
        $returnTime = (string) get_setting('locker_return_time', '16:30');
        $deadline = Carbon::now(self::LOCAL_TIMEZONE)->setTimeFromTimeString($returnTime);

        return [
            'timezone'        => self::LOCAL_TIMEZONE,
            'now'             => now()->toIso8601String(),
            'return_deadline' => $deadline->toIso8601String(),
            'is_after_hours'  => Carbon::now(self::LOCAL_TIMEZONE)->greaterThan($deadline),
        ];
    }
}
