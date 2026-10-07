<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestbookMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_no' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_no.required' => 'Nomor anggota (barcode member) wajib diisi/dipindai.',
        ];
    }
}
