<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photo = $this->photo_path ?: $this->avatar;
        if ($photo && !str_starts_with($photo, 'http://') && !str_starts_with($photo, 'https://')) {
            $cleaned = ltrim($photo, '/');
            if (str_starts_with($cleaned, 'storage/')) {
                $photo = asset($cleaned);
            } else {
                $photo = asset('storage/' . $cleaned);
            }
        }

        return [
            'id'                  => $this->id,
            'member_no'           => $this->member_no,
            'name'                => $this->name,
            'email'               => $this->email,
            'phone_number'        => $this->phone_number,
            'job_category'        => $this->job_category,
            'institution'         => $this->institution_name,
            'institution_name'    => $this->institution_name,
            'photo_path'          => $photo,
            'photo_url'           => $photo,
            'avatar'              => $this->avatar,
            'place_of_birth'      => $this->place_of_birth,
            'date_of_birth'       => $this->date_of_birth ? (is_string($this->date_of_birth) ? $this->date_of_birth : $this->date_of_birth->format('Y-m-d')) : null,
            'ktp_address'         => $this->ktp_address,
            'residential_address' => $this->residential_address,
            'created_at'          => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
