<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnsiteReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'guestbook_id'  => $this->guestbook_id,
            'guestbook'     => new GuestbookResource($this->whenLoaded('guestbook')),
            'member_id'     => $this->member_id,
            'member'        => new MemberResource($this->whenLoaded('member')),
            'collection_id' => $this->collection_id,
            'collection'    => new CollectionResource($this->whenLoaded('collection')),
            'read_at'       => $this->read_at?->toIso8601String(),
        ];
    }
}
