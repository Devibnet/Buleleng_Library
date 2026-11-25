<?php

namespace App\Http\Requests;

use App\Models\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCollectionRequest extends FormRequest
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
        $collectionId = $this->route('collection_id') ?? $this->route('collection');

        return [
            'barcode_no'            => ['sometimes', 'required', 'string', 'max:100', Rule::unique('collections', 'barcode_no')->ignore($collectionId)],
            'item_number'           => ['nullable', 'string', 'max:100'],
            'rfid_no'               => ['nullable', 'string', 'max:100'],
            'exemplar_call_number'  => ['nullable', 'string', 'max:100'],
            'procurement_date'      => ['nullable', 'date'],
            'source_type'           => ['nullable', 'string', 'max:100'],
            'source_name'           => ['nullable', 'string', 'max:255'],
            'currency'              => ['nullable', 'string', 'max:10'],
            'price'                 => ['nullable', 'numeric', 'min:0'],
            'library_location_code' => ['nullable', 'string', 'max:100'],
            'room_location_code'    => ['nullable', 'string', 'max:100'],
            'access_type'           => ['nullable', 'string', 'max:100'],
            'category'              => ['nullable', 'string', 'max:100'],
            'availability_status'   => ['nullable', 'string', Rule::in(['AVAILABLE', 'BORROWED', 'RESERVED', 'MAINTENANCE', 'LOST', 'QUARANTINED'])],
            'is_quarantine'         => ['nullable', 'boolean'],
        ];
    }

    /**
     * Status sirkulasi hanya boleh diubah oleh proses peminjaman/reservasi di loket.
     * Mengubahnya dari form edit membuat status eksemplar tidak cocok dengan catatan peminjaman
     * (contoh: eksemplar "dipinjam" padahal tidak ada yang meminjam). Menyimpan ulang status
     * yang sama tetap diperbolehkan agar form edit lain di eksemplar itu tidak terhalang.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $requested = $this->input('availability_status');
            if (! $requested) {
                return;
            }

            $collection = Collection::find($this->route('collection_id') ?? $this->route('collection'));
            if (! $collection || $collection->availability_status === $requested) {
                return;
            }

            $circulation = ['BORROWED', 'RESERVED', 'READY_FOR_PICKUP', 'PENDING_CHECK'];

            if (in_array($requested, $circulation, true)) {
                $validator->errors()->add(
                    'availability_status',
                    'Status "dipinjam" atau "direservasi" hanya bisa diberikan lewat menu Peminjaman atau Reservasi di loket.'
                );
            } elseif (in_array($collection->availability_status, $circulation, true)) {
                $validator->errors()->add(
                    'availability_status',
                    'Eksemplar ini sedang dalam proses sirkulasi. Selesaikan lewat menu Pengembalian atau Reservasi sebelum mengubah statusnya.'
                );
            }
        });
    }
}
