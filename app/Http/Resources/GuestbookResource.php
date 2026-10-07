<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestbookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'visit_type'          => $this->visit_type,
            'member_id'           => $this->member_id,
            'member'              => new MemberResource($this->whenLoaded('member')),
            'visitor_name'        => $this->visitor_name,
            'phone_number'        => $this->phone_number,
            'institution_name'    => $this->institution_name,
            'institution_address' => $this->institution_address,
            'institution_email'   => $this->institution_email,
            'address'             => $this->address,
            'total_personnel'     => (int) $this->total_personnel,
            'gender_male_count'   => (int) $this->gender_male_count,
            'gender_female_count' => (int) $this->gender_female_count,
            'job_breakdown'       => $this->job_breakdown ?? [],
            'education_breakdown' => $this->education_breakdown ?? [],
            'visit_date'          => $this->visit_date?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
