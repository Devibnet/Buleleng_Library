<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReserveLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('member')->check();
    }

    public function rules(): array
    {
        return [
            'catalog_id'  => ['required', 'integer', 'exists:catalogs,id'],
            'pickup_days' => ['nullable', 'integer', 'min:1', 'max:7'],
        ];
    }

    public function messages(): array
    {
        return [
            'catalog_id.required' => 'Katalog buku wajib dipilih.',
            'catalog_id.exists'   => 'Katalog buku tidak ditemukan.',
        ];
    }
}
