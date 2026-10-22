<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasCatalogId = $this->filled('catalog_id');

        return [
            // Target Catalog ID (Optional jika menambah stok eksemplar ke buku yang sudah ada)
            'catalog_id' => ['nullable', 'integer', 'exists:catalogs,id'],

            // Metadata Catalog (Wajib jika catalog_id tidak diisi)
            'title'                       => [$hasCatalogId ? 'nullable' : 'required', 'string', 'max:255'],
            'material_type'               => ['nullable', 'string', 'max:255'],
            'media_type'                  => ['nullable', 'string', 'max:255'],
            'subtitle'                    => ['nullable', 'string', 'max:255'],
            'statement_of_responsibility' => ['nullable', 'string', 'max:255'],
            'author_main'                 => ['nullable', 'string', 'max:255'],
            'author_corporate'            => ['nullable', 'string', 'max:255'],
            'author_additional_person'    => ['nullable', 'string', 'max:255'],
            'author_additional_corporate' => ['nullable', 'string', 'max:255'],
            'edition'                     => ['nullable', 'string', 'max:255'],
            'publish_city'                => ['nullable', 'string', 'max:255'],
            'publisher'                   => ['nullable', 'string', 'max:255'],
            'publish_year'                => ['nullable', 'string', 'max:10'],
            'pages'                       => ['nullable', 'string', 'max:100'],
            'dimension'                   => ['nullable', 'string', 'max:100'],
            'isbn'                        => ['nullable', 'string', 'max:50'],
            'issn'                        => ['nullable', 'string', 'max:50'],
            'ismn'                        => ['nullable', 'string', 'max:50'],
            'ddc_number'                  => ['nullable', 'string', 'max:50'],
            'catalog_call_number'         => ['nullable', 'string', 'max:100'],
            'subject_topic'               => ['nullable', 'string', 'max:255'],
            'language'                    => ['nullable', 'string', 'max:50'],
            'abstract'                    => ['nullable', 'string'],
            'serial_edition'              => ['nullable', 'string', 'max:255'],
            'serial_publish_date'         => ['nullable', 'date'],
            'serial_accompanying_material'=> ['nullable', 'string', 'max:255'],
            'serial_notes'                => ['nullable', 'string'],
            'is_quarantine'               => ['nullable', 'boolean'],

            // Metadata Collection (Physical Exemplar)
            'barcode_no'            => ['nullable', 'string', 'max:100', 'unique:collections,barcode_no'],
            'item_number'           => ['nullable', 'string', 'max:100'],
            'rfid_no'               => ['nullable', 'string', 'max:100'],
            'exemplar_call_number'  => ['nullable', 'string', 'max:100'],
            'procurement_date'      => ['nullable', 'date'],
            'source_type'           => ['nullable', 'string', 'max:100'],
            'source_name'           => ['nullable', 'string', 'max:255'],
            'currency'              => ['nullable', 'string', 'max:10'],
            'price'                 => ['nullable', 'numeric', 'min:0'],
            'library_location_code' => ['nullable', 'string', 'max:100'],
            'room_location_code'    => ['nullable', 'string', 'max:100'],
            'access_type'           => ['nullable', 'string', 'max:100'],
            'category'              => ['nullable', 'string', 'max:100'],
            'availability_status'   => ['nullable', 'string', Rule::in(['AVAILABLE', 'BORROWED', 'RESERVED', 'MAINTENANCE', 'LOST', 'QUARANTINED'])],
            'collection_is_quarantine' => ['nullable', 'boolean'],
        ];
    }
}
