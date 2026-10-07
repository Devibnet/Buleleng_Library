<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestbookGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor_name'        => ['required', 'string', 'max:255'],
            'phone_number'        => ['required', 'string', 'max:25'],
            'institution_name'    => ['required', 'string', 'max:255'],
            'institution_address' => ['nullable', 'string'],
            'institution_email'   => ['nullable', 'email', 'max:255'],
            'total_personnel'     => ['required', 'integer', 'min:1'],
            'gender_male_count'   => ['required', 'integer', 'min:0'],
            'gender_female_count' => ['required', 'integer', 'min:0'],
            'job_breakdown'       => ['nullable', 'array'],
            'education_breakdown' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'visitor_name.required'     => 'Nama Penanggung Jawab rombongan wajib diisi.',
            'phone_number.required'     => 'Nomor telepon/WhatsApp PJ rombongan wajib diisi.',
            'institution_name.required' => 'Nama instansi/sekolah/rombongan wajib diisi.',
            'total_personnel.required'  => 'Total personil rombongan wajib diisi.',
            'gender_male_count.required'   => 'Jumlah laki-laki wajib diisi.',
            'gender_female_count.required' => 'Jumlah perempuan wajib diisi.',
        ];
    }
}
