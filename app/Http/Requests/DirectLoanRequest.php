<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DirectLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('admin')->check();
    }

    public function rules(): array
    {
        return [
            'member_id'     => ['required', 'integer', 'exists:members,id'],
            'barcode_no'    => ['required', 'string', 'exists:collections,barcode_no'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required'  => 'Anggota wajib dipilih.',
            'member_id.exists'    => 'Anggota tidak ditemukan.',
            'barcode_no.required' => 'Barcode buku wajib diisi.',
            'barcode_no.exists'   => 'Barcode buku tidak ditemukan di sistem.',
        ];
    }
}
