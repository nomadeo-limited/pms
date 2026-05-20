<?php

namespace App\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'max_capacity' => 'nullable|integer|min:1',
            'amenities' => 'nullable|array',
            'images' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
