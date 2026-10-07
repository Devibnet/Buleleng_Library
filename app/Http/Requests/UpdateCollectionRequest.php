<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'barcode_no'            => ['required', 'string', 'max:100', Rule::unique('collections', 'barcode_no')->ignore($collectionId)],
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
}
