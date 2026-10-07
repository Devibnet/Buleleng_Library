<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'loan_number'      => $this->loan_number,
            'reservation_code' => $this->reservation_code,
            'status'           => $this->status,
            'member_id'        => $this->member_id,
            'member'           => new MemberResource($this->whenLoaded('member')),
            'collection_id'    => $this->collection_id,
            'collection'       => new CollectionResource($this->whenLoaded('collection')),
            'pickup_deadline'  => $this->pickup_deadline?->toIso8601String(),
            'borrow_date'      => $this->borrow_date?->toIso8601String(),
            'loan_date'        => $this->loan_date?->toDateString(),
            'due_date'         => $this->due_date?->toDateString(),
            'return_date'      => $this->return_date?->toDateString(),
            'late_days'        => (int) $this->late_days,
            'fine_amount'      => (float) $this->fine_amount,
            'created_by'       => $this->createdByAdmin?->name,
            'returned_by'      => $this->returnedByAdmin?->name,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
