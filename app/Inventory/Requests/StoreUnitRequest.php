<?php

namespace App\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'required|uuid|exists:properties,id',
            'room_type_id' => 'required|uuid|exists:room_types,id',
            'name' => 'required|string|max:255',
            'bed_category' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'room_id'  => 'nullable|uuid|exists:rooms,id',
        ];
    }
}
