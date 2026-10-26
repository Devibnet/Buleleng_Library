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
            'title'                       => ['sometimes', 'required', 'string', 'min:3', 'regex:/^[^\d]+$/', 'max:255'],
            'quarantine_reason'           => ['nullable', 'string', 'max:255'],
            'material_type'               => ['nullable', 'string', 'max:255'],
            'media_type'                  => ['nullable', 'string', 'max:255'],
            'subtitle'                    => ['nullable', 'string', 'min:3', 'max:255'],
            'statement_of_responsibility' => ['nullable', 'string', 'min:3', 'max:255'],
            'author_main'                 => ['required', 'string', 'min:3', 'regex:/^[^\d]+$/', 'max:255'],
            'author_corporate'            => ['nullable', 'string', 'max:255'],
            'author_additional_person'    => ['nullable', 'string', 'max:255'],
            'author_additional_corporate' => ['nullable', 'string', 'max:255'],
            'edition'                     => ['nullable', 'string', 'max:255'],
            'publish_city'                => ['nullable', 'string', 'regex:/^[^\d]+$/', 'max:255'],
            'publisher'                   => ['nullable', 'string', 'regex:/^[^\d]+$/', 'max:255'],
            'publish_year'                => ['nullable', 'string', 'regex:/^\d+$/', 'max:10'],
            'pages'                       => ['nullable', 'string', 'max:100'],
            'dimension'                   => ['nullable', 'string', 'max:100'],
            'isbn'                        => ['nullable', 'string', 'regex:/^[\d\-\s]+$/', 'max:50'],
            'issn'                        => ['nullable', 'string', 'regex:/^[\d\-\s]+$/', 'max:50'],
            'ismn'                        => ['nullable', 'string', 'max:50'],
            'ddc_number'                  => ['nullable', 'string', 'regex:/^[\d.]+$/', 'max:50'],
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

    /**
     * Custom message for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'       => 'Judul Utama wajib diisi.',
            'title.min'            => 'Judul Utama minimal harus terdiri dari 3 huruf.',
            'title.regex'          => 'Judul Utama wajib berupa huruf dan dilarang mengandung angka.',
            'subtitle.min'         => 'Anak Judul minimal harus terdiri dari 3 huruf.',
            'statement_of_responsibility.min' => 'Penanggung Jawab minimal harus terdiri dari 3 huruf.',
            'author_main.required' => 'Nama Pengarang Utama wajib diisi.',
            'author_main.min'      => 'Nama Pengarang Utama minimal harus terdiri dari 3 huruf.',
            'author_main.regex'    => 'Nama Pengarang Utama wajib berupa huruf dan dilarang mengandung angka.',
            'publish_city.regex'   => 'Tempat terbit wajib berupa huruf dan dilarang mengandung angka.',
            'publisher.regex'      => 'Nama Penerbit wajib berupa huruf dan dilarang mengandung angka.',
            'publish_year.regex'   => 'Tahun terbit wajib berupa angka.',
            'ddc_number.regex'     => 'No. Klas DDC wajib berupa angka.',
            'isbn.regex'           => 'ISBN / ISSN wajib berupa angka.',
            'issn.regex'           => 'ISBN / ISSN wajib berupa angka.',
        ];
    }
}
