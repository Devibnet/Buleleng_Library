<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\LockedIdentityNumber;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nomor identitas yang dikunci karena akunnya dihapus pemilik saat masih ada kasus Keranjang Bermasalah.
 * Petugas tidak melihat nomor lengkapnya: untuk membuka kunci, petugas mengetik nomor dari kartu asli
 * pemohon dan sistem mencocokkannya dengan sidik yang tersimpan.
 */
class LockedIdentityController extends Controller
{
    /** GET /admin/locked-identities?status=active|unlocked */
    public function index(Request $request): JsonResponse
    {
        $unlocked = $request->query('status') === 'unlocked';

        $items = LockedIdentityNumber::query()
            ->when($unlocked, fn ($q) => $q->whereNotNull('unlocked_at'), fn ($q) => $q->active())
            ->orderByDesc($unlocked ? 'unlocked_at' : 'locked_at')
            ->limit(200)
            ->get()
            ->map(fn (LockedIdentityNumber $lock) => $this->present($lock));

        return response()->json([
            'success' => true,
            'data'    => $items,
            'active_count' => LockedIdentityNumber::active()->count(),
        ]);
    }

    /** POST /admin/locked-identities/{id}/unlock  { nik, note } */
    public function unlock(Request $request, int $id): JsonResponse
    {
        $lock = LockedIdentityNumber::active()->find($id);
        if (! $lock) {
            return response()->json([
                'success' => false,
                'message' => 'Kunci ini tidak ditemukan atau sudah dibuka.',
            ], 404);
        }

        $label = Member::IDENTITY_NUMBER_RULES[$lock->number_kind]['label'] ?? 'Nomor identitas';
        $validated = $request->validate([
            'nik'  => [$lock->nik_hash ? 'required' : 'nullable', 'string', 'max:32'],
            'note' => ['required', 'string', 'max:500'],
        ], [
            'nik.required'  => "Ketik {$label} dari kartu asli pemohon.",
            'note.required' => 'Tulis bagaimana kasusnya diselesaikan.',
        ]);

        if ($lock->nik_hash) {
            $number = Member::normalizeIdentityNumber($lock->number_kind, $validated['nik'] ?? '');
            if (! hash_equals($lock->nik_hash, (string) Member::identityHash($number))) {
                return response()->json([
                    'success' => false,
                    'message' => "{$label} yang diketik tidak sama dengan nomor yang dikunci. Periksa lagi kartu asli pemohon.",
                    'errors'  => ['nik' => ["{$label} tidak sama dengan nomor yang dikunci."]],
                ], 422);
            }
        }

        $admin = $request->user();
        $lock->update([
            'unlocked_at'      => now(),
            'unlocked_by'      => $admin instanceof Admin ? $admin->id : null,
            'unlocked_by_name' => $admin instanceof Admin ? $admin->name : null,
            'unlock_note'      => $validated['note'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Kunci {$label} {$lock->masked_number} sudah dibuka. Pemiliknya bisa mendaftar lagi.",
            'data'    => $this->present($lock->fresh()),
        ]);
    }

    private function present(LockedIdentityNumber $lock): array
    {
        $cases = collect($lock->cases ?? []);

        return [
            'id'               => $lock->id,
            'number_kind'      => $lock->number_kind,
            'number_label'     => Member::IDENTITY_NUMBER_RULES[$lock->number_kind]['label'] ?? 'Nomor identitas',
            'masked_number'    => $lock->masked_number,
            'has_number'       => $lock->nik_hash !== null,
            'former_member_no' => $lock->former_member_no,
            'cases'            => $cases->values()->all(),
            'outstanding'      => $cases->sum(fn ($c) => max(0, (float) ($c['fine_amount'] ?? 0) - (float) ($c['paid_amount'] ?? 0))),
            'locked_at'        => $lock->locked_at?->toIso8601String(),
            'unlocked_at'      => $lock->unlocked_at?->toIso8601String(),
            'unlocked_by_name' => $lock->unlocked_by_name,
            'unlock_note'      => $lock->unlock_note,
        ];
    }
}
