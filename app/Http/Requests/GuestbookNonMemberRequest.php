<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestbookNonMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor_name'     => ['required', 'string', 'max:255'],
            'phone_number'     => ['nullable', 'string', 'max:25'],
            'address'          => ['nullable', 'string'],
            'gender'           => ['required', 'in:M,F'],
            'institution_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'visitor_name.required' => 'Nama pengunjung wajib diisi.',
            'gender.required'       => 'Jenis kelamin wajib dipilih.',
            'gender.in'             => 'Jenis kelamin harus M (Laki-laki) atau F (Perempuan).',
        ];
    }
}
