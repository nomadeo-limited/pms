<?php

namespace App\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_type_id' => 'sometimes|uuid|exists:room_types,id',
            'name'         => 'sometimes|string|max:255',
            'floor'        => 'nullable|string|max:100',
            'is_active'    => 'sometimes|boolean',
        ];
    }
}
