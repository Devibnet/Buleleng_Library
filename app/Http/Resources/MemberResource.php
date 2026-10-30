<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photo = $this->photo_url;

        // Cek kasus aktif di Keranjang Bermasalah
        $activeProblemsCount = $this->relationLoaded('memberCarts')
            ? $this->memberCarts->where('is_resolved', false)->count()
            : $this->memberCarts()->where('is_resolved', false)->count();

        $isProblematic = $activeProblemsCount > 0
            || str_contains(strtolower($this->status ?? ''), 'dibekukan')
            || str_contains(strtolower($this->status ?? ''), 'bermasalah');

        $status = $this->status ?: 'Aktif Bebas Pustaka';
        if ($activeProblemsCount > 0 && str_starts_with(strtolower($status), 'aktif')) {
            $status = 'Akses Dibekukan';
        }

        return [
            'id'                    => $this->id,
            'member_no'             => $this->member_no,
            'nik'                   => $this->nik,
            'name'                  => $this->name,
            'gender'                => $this->gender ?: 'L',
            'email'                 => $this->email,
            'phone_number'          => $this->phone_number,
            'job_category'          => $this->job_category,
            'institution'           => $this->institution_name,
            'institution_name'      => $this->institution_name,
            'max_loans'             => $this->max_loans ?: 3,
            'status'                => $status,
            'is_problematic'        => $isProblematic,
            'active_problems_count' => $activeProblemsCount,
            'valid_until'           => $this->valid_until ? (is_string($this->valid_until) ? $this->valid_until : $this->valid_until->format('Y-m-d')) : null,
            'photo_path'            => $photo,
            'photo_url'             => $photo,
            'avatar'                => $this->avatar,
            'place_of_birth'        => $this->place_of_birth,
            'date_of_birth'         => $this->date_of_birth ? (is_string($this->date_of_birth) ? $this->date_of_birth : $this->date_of_birth->format('Y-m-d')) : null,
            'ktp_address'           => $this->ktp_address,
            'residential_address'   => $this->residential_address,
            'created_at'            => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
