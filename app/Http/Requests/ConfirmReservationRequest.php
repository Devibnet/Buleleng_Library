<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('admin')->check();
    }

    public function rules(): array
    {
        return [
            'reservation_code' => ['required', 'string', 'exists:loans,reservation_code'],
            'duration_days'    => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'reservation_code.required' => 'Kode reservasi wajib diisi.',
            'reservation_code.exists'   => 'Kode reservasi tidak ditemukan.',
        ];
    }
}
