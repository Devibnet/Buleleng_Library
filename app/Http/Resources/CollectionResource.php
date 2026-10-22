<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'catalog_id'            => $this->catalog_id,
            'item_number'           => $this->item_number,
            'barcode_no'            => $this->barcode_no,
            'rfid_no'               => $this->rfid_no,
            'exemplar_call_number'  => $this->exemplar_call_number,
            'procurement_date'      => $this->procurement_date ? (is_string($this->procurement_date) ? $this->procurement_date : $this->procurement_date->format('Y-m-d')) : null,
            'source_type'           => $this->source_type,
            'source_name'           => $this->source_name,
            'currency'              => $this->currency,
            'price'                 => $this->price,
            'library_location_code' => $this->library_location_code,
            'room_location_code'    => $this->room_location_code,
            'access_type'           => $this->access_type,
            'category'              => $this->category,
            'availability_status'   => $this->availability_status,
            'is_quarantine'         => (bool) $this->is_quarantine,
            'catalog'               => new CatalogResource($this->whenLoaded('catalog')),
            'created_at'            => $this->created_at?->toDateTimeString(),
            'updated_at'            => $this->updated_at?->toDateTimeString(),
        ];
    }
}
