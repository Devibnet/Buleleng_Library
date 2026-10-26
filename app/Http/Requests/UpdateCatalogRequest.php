<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogRequest extends FormRequest
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
        return [
            'title'                       => ['sometimes', 'required', 'string', 'max:255'],
            'quarantine_reason'           => ['nullable', 'string', 'max:255'],
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
        ];
    }
}
