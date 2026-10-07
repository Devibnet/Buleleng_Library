<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'subtitle'         => $this->subtitle,
            'author_main'      => $this->author_main,
            'publisher'        => $this->publisher,
            'publish_year'     => $this->publish_year,
            'isbn'             => $this->isbn,
            'total_copies'     => (int) $this->total_copies,
            'available_copies' => (int) $this->available_copies,
            'is_quarantine'    => (bool) $this->is_quarantine,
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),
        ];
    }
}
