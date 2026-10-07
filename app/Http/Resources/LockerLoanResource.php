<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LockerLoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'loan_number'         => $this->loan_number,
            'member_id'           => $this->member_id,
            'member'              => new MemberResource($this->whenLoaded('member')),
            'locker_id'           => $this->locker_id,
            'locker'              => new LockerResource($this->whenLoaded('locker')),
            'deposit_type'        => $this->deposit_type,
            'borrow_date'         => $this->borrow_date?->toIso8601String(),
            'return_date'         => $this->return_date?->toIso8601String(),
            'violation_note'      => $this->violation_note,
            'status'              => $this->status,
            'processed_by'        => $this->processedByAdmin?->name,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
