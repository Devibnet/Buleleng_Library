<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberCartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $fine = (float) ($this->fine_amount ?? 0);
        $paid = (float) ($this->paid_amount ?? 0);
        $remaining = max(0, $fine - $paid);

        $member = $this->relationLoaded('member') && $this->member ? $this->member : null;
        $memberData = null;
        if ($member) {
            $age = null;
            if ($member->date_of_birth) {
                try {
                    $dob = Carbon::parse($member->date_of_birth);
                    $age = $dob->age;
                } catch (\Throwable $e) {
                    $age = null;
                }
            }

            $memberData = [
                'id'               => $member->id,
                'member_no'        => $member->member_no,
                'name'             => $member->name,
                'gender'           => $member->gender ?: 'L',
                'age'              => $age,
                'job_category'     => $member->job_category ?: 'UMUM',
                'institution_name' => $member->institution_name ?: '-',
                'phone_number'     => $member->phone_number ?: '',
                'email'            => $member->email ?: '',
                'photo_url'        => $member->photo_path ? (str_starts_with($member->photo_path, 'http') ? $member->photo_path : asset('storage/' . ltrim($member->photo_path, '/'))) : null,
                'status'           => $member->status ?: 'Aktif Bebas Pustaka',
                'residential_address' => $member->residential_address,
                'ktp_address'      => $member->ktp_address,
                'place_of_birth'   => $member->place_of_birth,
                'date_of_birth'    => $member->date_of_birth ? Carbon::parse($member->date_of_birth)->format('Y-m-d') : null,
            ];
        }

        // Format problem type label
        $problemTypeLabels = [
            'BUKU_HILANG'       => 'Buku Hilang',
            'TELAT'             => 'Keterlambatan / Telat',
            'DENDA_MENUMPUK'    => 'Denda Menumpuk',
            'KOLEKSI_RUSAK'     => 'Koleksi Rusak Berat',
            'ANOMALI_IDENTITAS' => 'Anomali NIK / Ganda',
            'LAINNYA'           => 'Pelanggaran Lainnya',
        ];

        // Format action status label
        $statusLabels = [
            'AKSES_DIBEKUKAN'      => 'Akses Dibekukan',
            'PERINGATAN_2'         => 'Peringatan ke-2',
            'PERINGATAN_1'         => 'Peringatan ke-1',
            'DALAM_MEDIASI'        => 'Dalam Mediasi',
            'MENUNGGU_KONFIRMASI'  => 'Menunggu Konfirmasi',
            'SELESAI'              => 'Selesai / Teratasi',
        ];

        $reportedDateFormatted = null;
        if ($this->reported_date) {
            try {
                $reportedDateFormatted = Carbon::parse($this->reported_date)->translatedFormat('d F Y');
            } catch (\Throwable $e) {
                $reportedDateFormatted = (string) $this->reported_date;
            }
        }

        return [
            'id'                     => $this->id,
            'member_id'              => $this->member_id,
            'member'                 => $memberData,
            'loan_id'                => $this->loan_id,
            'collection_id'          => $this->collection_id,
            'problem_type'           => $this->problem_type,
            'problem_type_label'     => $problemTypeLabels[$this->problem_type] ?? $this->problem_type,
            'item_title'             => $this->item_title ?: '-',
            'item_call_number'       => $this->item_call_number,
            'item_barcode'           => $this->item_barcode,
            'description'            => $this->description,
            'fine_amount'            => $fine,
            'paid_amount'            => $paid,
            'remaining_amount'       => $remaining,
            'remaining_debt'         => $remaining,
            'handled_by'             => $this->handled_by ?: 'Petugas Sirkulasi',
            'action_status'          => $this->action_status,
            'action_status_label'    => $statusLabels[$this->action_status] ?? $this->action_status,
            'action_notes'           => $this->action_notes,
            'reported_date'          => $this->reported_date ? Carbon::parse($this->reported_date)->format('Y-m-d') : null,
            'reported_date_formatted'=> $reportedDateFormatted,
            'late_duration_text'     => $this->late_duration_text,
            'followup_history'       => is_array($this->followup_history) ? $this->followup_history : [],
            'is_resolved'            => (bool) $this->is_resolved,
            'resolution_notes'       => $this->resolution_notes,
            'resolved_at'            => $this->resolved_at ? Carbon::parse($this->resolved_at)->toIso8601String() : null,
            'created_at'             => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at'             => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
