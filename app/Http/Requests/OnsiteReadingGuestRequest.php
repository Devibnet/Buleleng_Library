<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnsiteReadingGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guestbook_id'    => ['required', 'integer', 'exists:guestbooks,id'],
            'collection_code' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'guestbook_id.required'    => 'ID buku tamu (guestbook_id) wajib diisi.',
            'guestbook_id.exists'      => 'Buku tamu tidak ditemukan di sistem.',
            'collection_code.required' => 'Kode koleksi (barcode buku) wajib diisi/dipindai.',
        ];
    }
}
