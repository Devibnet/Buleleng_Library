<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'catalog_id'          => $this->catalog_id,
            'item_number'         => $this->item_number,
            'barcode_no'          => $this->barcode_no,
            'rfid_no'             => $this->rfid_no,
            'availability_status' => $this->availability_status,
            'is_quarantine'       => (bool) $this->is_quarantine,
            'catalog'             => new CatalogResource($this->whenLoaded('catalog')),
        ];
    }
}
