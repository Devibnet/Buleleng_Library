<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'material_type'               => $this->material_type,
            'media_type'                  => $this->media_type,
            'title'                       => $this->title,
            'subtitle'                    => $this->subtitle,
            'statement_of_responsibility' => $this->statement_of_responsibility,
            'author_main'                 => $this->author_main,
            'author_corporate'            => $this->author_corporate,
            'author_additional_person'    => $this->author_additional_person,
            'author_additional_corporate' => $this->author_additional_corporate,
            'edition'                     => $this->edition,
            'publish_city'                => $this->publish_city,
            'publisher'                   => $this->publisher,
            'publish_year'                => $this->publish_year,
            'pages'                       => $this->pages,
            'dimension'                   => $this->dimension,
            'isbn'                        => $this->isbn,
            'issn'                        => $this->issn,
            'ismn'                        => $this->ismn,
            'ddc_number'                  => $this->ddc_number,
            'catalog_call_number'         => $this->catalog_call_number,
            'subject_topic'               => $this->subject_topic,
            'language'                    => $this->language,
            'abstract'                    => $this->abstract,
            'total_copies'                => (int) $this->total_copies,
            'available_copies'            => (int) $this->available_copies,
            'is_quarantine'               => (bool) $this->is_quarantine,
            'collections'                 => $this->whenLoaded('collections'),
            'created_at'                  => $this->created_at?->toDateTimeString(),
            'updated_at'                  => $this->updated_at?->toDateTimeString(),
        ];
    }
}
