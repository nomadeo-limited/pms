<?php

namespace App\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'bed_category' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
