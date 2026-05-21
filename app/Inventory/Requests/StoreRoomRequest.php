<?php

namespace App\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id'  => 'required|uuid|exists:properties,id',
            'room_type_id' => 'required|uuid|exists:room_types,id',
            'name'         => 'required|string|max:255',
            'floor'        => 'nullable|string|max:100',
            'is_active'    => 'sometimes|boolean',
        ];
    }
}
