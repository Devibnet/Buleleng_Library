<?php

namespace App\Http\Requests;

use App\Models\Guestbook;
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
            'visitor_name'     => ['required', 'string', 'min:3', 'max:255', 'regex:' . Guestbook::PERSON_NAME_REGEX],
            'phone_number'     => ['nullable', 'string', 'min:3', 'max:25', 'regex:' . Guestbook::PHONE_REGEX],
            'address'          => ['nullable', 'string'],
            'gender'           => ['required', 'in:M,F'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'job'              => ['required', 'in:' . implode(',', array_keys(Guestbook::JOB_LABELS))],
            'education'        => ['required', 'in:' . implode(',', array_keys(Guestbook::EDUCATION_LABELS))],
            'visit_purpose'    => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'visitor_name.required' => 'Nama pengunjung wajib diisi.',
            'visitor_name.min'      => 'Nama pengunjung minimal 3 karakter.',
            'visitor_name.regex'    => 'Nama pengunjung hanya boleh berisi huruf.',
            'phone_number.min'      => 'Nomor telepon minimal 3 karakter.',
            'phone_number.regex'    => 'Nomor telepon hanya boleh berisi angka.',
            'gender.required'       => 'Jenis kelamin wajib dipilih.',
            'gender.in'             => 'Jenis kelamin harus M (Laki-laki) atau F (Perempuan).',
            'job.required'          => 'Pekerjaan / profesi wajib dipilih.',
            'job.in'                => 'Pekerjaan / profesi tidak valid.',
            'education.required'    => 'Pendidikan terakhir wajib dipilih.',
            'education.in'          => 'Pendidikan terakhir tidak valid.',
        ];
    }
}
