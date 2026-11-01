<?php

namespace App\Http\Requests;

use App\Models\Guestbook;
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
            'visitor_name'        => ['required', 'string', 'min:3', 'max:255', 'regex:' . Guestbook::PERSON_NAME_REGEX],
            'phone_number'        => ['required', 'string', 'min:3', 'max:25', 'regex:' . Guestbook::PHONE_REGEX],
            'institution_name'    => ['required', 'string', 'min:3', 'max:255'],
            'institution_address' => ['nullable', 'string'],
            'institution_email'   => ['nullable', 'email', 'max:255'],
            'institution_phone'   => ['nullable', 'string', 'min:3', 'max:25', 'regex:' . Guestbook::PHONE_REGEX],
            'visit_purpose'       => ['required', 'string', 'max:255'],
            'total_personnel'     => ['required', 'integer', 'min:1'],
            'gender_male_count'   => ['required', 'integer', 'min:0'],
            'gender_female_count' => ['required', 'integer', 'min:0'],
            'job_breakdown'       => ['nullable', 'array'],
            'job_breakdown.*'     => ['integer', 'min:0'],
            'education_breakdown' => ['nullable', 'array'],
            'education_breakdown.*' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'visitor_name.required'     => 'Nama Penanggung Jawab rombongan wajib diisi.',
            'visitor_name.min'          => 'Nama ketua rombongan minimal 3 karakter.',
            'visitor_name.regex'        => 'Nama ketua rombongan hanya boleh berisi huruf.',
            'phone_number.required'     => 'Nomor telepon/WhatsApp PJ rombongan wajib diisi.',
            'phone_number.min'          => 'Nomor telepon ketua rombongan minimal 3 karakter.',
            'phone_number.regex'        => 'Nomor telepon ketua rombongan hanya boleh berisi angka.',
            'institution_name.required' => 'Nama instansi/sekolah/rombongan wajib diisi.',
            'institution_name.min'      => 'Nama instansi minimal 3 karakter.',
            'institution_phone.min'     => 'Nomor telepon instansi minimal 3 karakter.',
            'institution_phone.regex'   => 'Nomor telepon instansi hanya boleh berisi angka.',
            'total_personnel.required'  => 'Total personil rombongan wajib diisi.',
            'visit_purpose.required'    => 'Keperluan kunjungan rombongan wajib diisi.',
            'institution_email.email'   => 'Format email instansi tidak valid.',
            'gender_male_count.required'   => 'Jumlah laki-laki wajib diisi.',
            'gender_female_count.required' => 'Jumlah perempuan wajib diisi.',
        ];
    }
}
